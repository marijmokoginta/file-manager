# File Manager for Laravel

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

$url = FileUrl::url('uploads/images/image.jpg'); // Local or driver-specific
```

## 🗃 Supported Drivers
- ✅ Local (default)
- 🔜 S3, Firebase, Custom drivers

Configure in `config/file-manager.php`:
```php
'driver' => 'local', // or 'firebase', 's3'
```

## 📄 License
[MIT License](LICENSE) © Marij Mokoginta (M2code)
