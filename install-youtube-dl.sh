DOWNLOAD_URL="https://github.com/yt-dlp/yt-dlp/releases/download/2023.12.30/yt-dlp_linux"
CORRECT_SHA256="0f606eab88c629884e673ae69355fbd5d0caf035f299a3f32e104bbf4ff90063"
DOWNLOADED_FILE_PATH="./vendor/bin/youtube-dl"

if test -f "$DOWNLOADED_FILE_PATH" && [ "$DOWNLOADED_FILE_SHA256" == "$CORRECT_SHA256" ]; then
    exit 0
fi

echo "Downloading youtube-dl from $DOWNLOAD_URL"

curl -L "$DOWNLOAD_URL" --output "$DOWNLOADED_FILE_PATH"

DOWNLOADED_FILE_SHA256="$(sha256sum -z ./vendor/bin/youtube-dl | cut -d ' ' -f 1)"

if [ "$DOWNLOADED_FILE_SHA256" != "$CORRECT_SHA256" ]; then
    rm "$DOWNLOADED_FILE_PATH"
    echo "Error downloading youtube-dl"
    exit 1
fi

chmod +x "$DOWNLOADED_FILE_PATH"
