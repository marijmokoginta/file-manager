# 🛠️ File Manager for Laravel (WIP)

> ⚠️ **This package is currently under active development. Breaking changes may occur. Contributions are welcome!**

📦 A modular, clean-architecture-based Laravel package to manage file operations — image, video, documents — with support for multiple drivers (local, cloud, Firebase, etc), progressive images, and flexible configuration.

---

## 🔧 Features

- 📁 Save, move, delete, and manage files (images, videos, docs, etc.)
- 🧠 Auto-detect file type & handle accordingly
- 🌁 Image processing (blur preview, low quality, watermark)
- ☁️ Extensible storage drivers: local, S3, Firebase, etc.
- ⚙️ Clean architecture (DDD-friendly & testable)
- 🧩 Facade and fluent Uploader API
- 🔐 Support for signed/dynamic URLs

---

## 🚀 Installation

```bash
composer require m2code/file-manager
```

## 🛠 Publish Configuration
```bash
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

## 📂 Basic Usage
### Save file using facade (no config):
```php
use M2code\FileManager\Facades\FileManager;

$path = FileManager::save($request->file('image'), 'uploads');
```

### Upload image with processing:
```php
use M2code\FileManager\Uploaders\ImageUploader;

$uploader = new ImageUploader();

$result = $uploader
    ->enableBlur()
    ->enableLowQuality()
    ->upload($request->file('photo'), 'uploads/images');

$result->path;            // Original file path
$result->lowQualityPath;  // Low quality version
$result->blurhash;        // Blurhash string
```

## 📡 Get file URL
```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('uploads/images/image.jpg'); // Local or driver-specific
```

## 🗃 Supported Drivers
- ✅ Local (default)
- 🔜 S3, Firebase, Custom drivers

Configure in `config/file-manager.php`:
```php
'driver' => 'local', // or 'firebase', 's3'
```

## 🧩 Optional Requirement: Imagick (Recommended)

This package uses :contentReference[oaicite:0]{index=0} for image processing features such as:

- Blurhash generation
- Low quality image variants
- Watermark (when enabled)

While it can work without Imagick, **it is strongly recommended** to install the Imagick extension for better performance and compatibility.

---

## ⚙️ Why Imagick?

Compared to GD:
- Better image quality
- Faster processing for large images
- More advanced image manipulation capabilities

If you're serious about handling images, GD is… let's say, “minimum effort mode”.

---

## 💻 Installation Guide

### 🪟 Windows

1. Download Imagick DLL from:
   👉 https://windows.php.net/downloads/pecl/releases/imagick/

2. Choose version that matches:
    - Your PHP version
    - Thread safety (TS/NTS)
    - Architecture (x64/x86)

3. Copy `.dll` file to:
`ext/`

4. Enable in `php.ini`:
```ini
extension=imagick
```

5. Restart your web server

### 🍎 macOS
Using Homebrew:
```bash
brew install imagemagick
pecl install imagick
```

Then enable in php.ini:
```ini
extension=imagick
```

### 🐧 Linux (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install imagemagick
sudo apt install php-imagick
```

Restart PHP / Web Server:
```bash
sudo service php-fpm restart
# or
sudo service apache2 restart
```

### 🐧 Linux (CentOS/RHEL)
```bash
sudo yum install epel-release
sudo yum install ImageMagick ImageMagick-devel
sudo pecl install imagick
```

Enable in php.ini:
```ini
extension=imagick
```

## ✅ Verify Installation
Run:
```php
php -m | grep imagick
```

If installed correctly, you should see:
```bash
imagick
```

## 📄 License
[MIT License](LICENSE) © Marij Mokoginta (M2code)
