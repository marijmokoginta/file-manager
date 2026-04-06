# 🛠️ File Manager for Laravel (WIP)

> ⚠️ **This package is currently under active development. Breaking changes may occur. Contributions are welcome!**

📦 A modular, clean-architecture-based Laravel package to manage file operations — image, video, documents — with support for multiple drivers (local, cloud, Firebase, etc), progressive images, and flexible configuration.

---

## 🔧 Features

- 📁 Save and delete files (single or batch)
- 🧠 Auto-detect file type & handle accordingly
- 🌁 Image processing (blurhash, low quality, watermark)
- 🧩 Structured variants (`original`, `low_quality`, `watermark`, future-ready)
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

$result = FileManager::save($request->file('image'), 'uploads');
$result->filePath;
```

### Upload image with processing:
```php
use M2code\FileManager\Application\Uploader\ImageUploader;

$result = ImageUploader::make()
    ->blur()
    ->lowQuality()
    ->watermark()
    ->upload($request->file('photo'), 'uploads/images');

$result->variants->get('original')?->path;
$result->variants->get('low_quality')?->path;
$result->variants->get('watermark')?->path;
$result->blurhash;

// Backward-compatible fields (still available):
$result->path;
$result->lowQualityPath;
$result->watermarkPath;
```

### Delete files:
```php
use M2code\FileManager\Facades\FileManager;

FileManager::delete('uploads/images/file.jpg');

FileManager::deleteMany([
    'uploads/images/a.jpg',
    'uploads/images/b.jpg',
]);

// Delete all variants from upload result:
FileManager::deleteVariants($result->variants);
```

## 📡 Get file URL
```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('uploads/images/image.jpg'); // Local or driver-specific
$signed = FileUrl::getSignedUrl('uploads/images/image.jpg', now()->addMinutes(5));
```

## 🗃 Supported Drivers
- ✅ Local (default)
- 🔜 S3, Firebase, Custom drivers

Configure in `config/file-manager.php`:
```php
'default_driver' => 'local',
'default_deleter' => 'local',
'default_url_generator' => 'local',
```

## 🧩 Optional Requirement: Imagick (Recommended)

This package uses `intervention/image` with Imagick driver for image processing features such as:

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

## ✅ Current Stable Flow

- Upload image (with optional variants)
- Read variant paths from `ImageUploadResult::variants`
- Generate URL / signed URL
- Delete single file / batch / all variants safely

## 📄 License
[MIT License](LICENSE) © Marij Mokoginta (M2code)
