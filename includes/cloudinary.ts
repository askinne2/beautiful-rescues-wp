import path from 'path';
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { v2 as cloudinary } from "cloudinary";
import { type Image } from "@shared/schema";
import { imageCache } from './cache';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Load environment variables from .env.local and .env
dotenv.config({ path: path.resolve(__dirname, '..', '.env.local') });
dotenv.config();

// Initialize Cloudinary
cloudinary.config({
  cloud_name: process.env.VITE_CLOUDINARY_CLOUD_NAME || 'dgnb4yyrc',
  api_key: process.env.CLOUDINARY_API_KEY,
  api_secret: process.env.CLOUDINARY_API_SECRET,
  secure: true
});

// Log config to debug
console.log('Cloudinary configuration:', {
  cloud_name: process.env.VITE_CLOUDINARY_CLOUD_NAME || 'dgnb4yyrc',
  hasApiKey: !!process.env.CLOUDINARY_API_KEY,
  hasApiSecret: !!process.env.CLOUDINARY_API_SECRET
});

export function generateImageUrl(publicId: string): string {
  const baseUrl = `https://res.cloudinary.com/${process.env.VITE_CLOUDINARY_CLOUD_NAME || "dgnb4yyrc"}/image/upload`;
  const transformations = [
    'c_fill,w_800,h_800',
    'l_br-watermark_lvbxtf,o_50,w_0.4,g_south_east',
    'q_auto,f_auto'
  ].join('/');

  return `${baseUrl}/${transformations}/${publicId}`;
}

export async function getImagesFromFolder(
  category?: string,
  limit: number = 20,
  search?: string,
  sort: string = "random"
): Promise<Image[]> {
  try {
    console.log('Starting Cloudinary image fetch with params:', { category, limit, search, sort });

    // Debug Cloudinary config
    console.log('Cloudinary config check:', {
      isConfigured: cloudinary.config().cloud_name && cloudinary.config().api_key && cloudinary.config().api_secret,
      cloudName: cloudinary.config().cloud_name
    });

    // Don't cache random sorts
    if (sort !== "random") {
      const cacheKey = imageCache.generateKey(category, search, sort);
      const cached = await imageCache.get(cacheKey);
      
      if (cached) {
        console.log('Returning cached images for:', { category, search, sort });
        return cached;
      }
    }

    console.log('Starting image fetch with params:', { category, limit, search, sort });

    // Modified search expression to get all images from Cats folder when no category is selected
    const searchExpression = category 
      ? `resource_type:image AND folder=Cats/${category}`
      : 'resource_type:image AND folder:Cats/*';

    // Log the search expression being used
    console.log('Cloudinary search expression:', searchExpression);

    // Always get more results for better randomization when no category is selected
    const maxResults = category ? limit : 200;

    try {
      const results = await cloudinary.search
        .expression(searchExpression)
        .sort_by('created_at', sort === 'oldest' ? 'asc' : 'desc')
        .max_results(maxResults)
        .execute();
      
      console.log('Cloudinary search returned:', {
        resourceCount: results.resources?.length || 0,
        totalCount: results.total_count,
        hasResources: !!results.resources
      });

      console.log('Search API Response:', {
        resourceCount: results.resources?.length || 0,
        firstResource: results.resources?.[0],
        total_count: results.total_count
      });

      if (!results?.resources?.length) {
        console.log('No images found');
        return [];
      }

      let images = results.resources.map((resource: any) => {
        const categoryPath = resource.asset_folder || resource.public_id;
        const categoryName = categoryPath.split('/')[1] || 'uncategorized';
        
        return {
          id: resource.public_id,
          title: resource.filename || '',
          description: resource.context?.caption || '',
          imageUrl: generateImageUrl(resource.public_id),
          category: categoryName,
          isActive: true,
          createdAt: new Date(resource.created_at)
        };
      });

      // If no category is selected, ensure we get a random mix from all categories
      if (!category) {
        // Group images by category
        const imagesByCategory = images.reduce((acc: { [key: string]: any[] }, img) => {
          acc[img.category] = acc[img.category] || [];
          acc[img.category].push(img);
          return acc;
        }, {});

        // Get random images from each category
        const categories = Object.keys(imagesByCategory);
        const imagesPerCategory = Math.ceil(limit / categories.length);
        
        images = categories.flatMap(cat => {
          const categoryImages = imagesByCategory[cat];
          // Shuffle category images
          return categoryImages
            .sort(() => Math.random() - 0.5)
            .slice(0, imagesPerCategory);
        });

        // Final shuffle of the selected images
        images = images
          .sort(() => Math.random() - 0.5)
          .slice(0, limit);
      }

      // Apply search filter if provided
      if (search) {
        const searchLower = search.toLowerCase();
        images = images.filter(img =>
          img.title.toLowerCase().includes(searchLower) ||
          img.description.toLowerCase().includes(searchLower)
        );
      }

      // Apply sorting only if not random
      if (sort !== "random") {
        images = sortImages(images, sort);
      }

      // Cache the results if not random
      if (sort !== "random") {
        const cacheKey = imageCache.generateKey(category, search, sort);
        await imageCache.set(cacheKey, images);
      }

      return images;
    } catch (cloudinaryError) {
      console.error('Cloudinary API error:', cloudinaryError);
      throw cloudinaryError;
    }
  } catch (error) {
    console.error('Error in getImagesFromFolder:', error);
    throw error;
  }
}

export const getSignedUrl = (publicId: string) => {
  if (typeof window !== 'undefined') {
    throw new Error('getSignedUrl can only be called server-side');
  }

  return cloudinary.url(publicId, {
    secure: true,
    resource_type: 'image',
    type: 'private',
    sign_url: true,
    version: Math.round(new Date().getTime() / 1000),
  });
};


function sortImages(images: Image[], sortBy: string): Image[] {
  switch (sortBy) {
    case "oldest":
      return [...images].sort((a, b) =>
        (a.createdAt?.getTime() || 0) - (b.createdAt?.getTime() || 0)
      );
    case "title":
      return [...images].sort((a, b) => a.title.localeCompare(b.title));
    case "title-desc":
      return [...images].sort((a, b) => b.title.localeCompare(a.title));
    case "newest":
      return [...images].sort((a, b) =>
        (b.createdAt?.getTime() || 0) - (a.createdAt?.getTime() || 0)
      );
    default:
      return images;
  }
}

export function getImageById(id: string): string | undefined {
  if (!id) return undefined;
  return generateImageUrl(id);
}

export function getImageDownloadUrl(publicId: string): string {
  const baseUrl = `https://res.cloudinary.com/${process.env.VITE_CLOUDINARY_CLOUD_NAME || "dgnb4yyrc"}/image/upload`;
  const transformations = [
    'q_auto:best',  // Use best quality
    'f_auto',      // Auto format
    'fl_attachment:image', // Force download with proper content type
    'dpr_auto',    // Auto device pixel ratio
    'w_auto',      // Auto width
    'c_scale'      // Scale to maintain aspect ratio
  ].join('/');

  return `${baseUrl}/${transformations}/${publicId}`;
}
