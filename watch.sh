#!/bin/bash

# Function to clear screen
clear_screen() {
    clear
}

# Start tail in background
tail -f "/Users/andrewskinner/Local Sites/beautiful-rescues-2025/logs/php/error.log" &

# Store the tail process ID
TAIL_PID=$!

# Trap Ctrl+C to kill tail process
trap 'kill $TAIL_PID; exit' INT

# Main loop to handle key presses
while true; do
    # Read a single character
    read -n 1 key
    
    # If 'c' is pressed, clear the screen
    if [ "$key" = "c" ]; then
        clear_screen
    fi
done