# iOS Platform Setup Guide — SP-4.0

## پیش‌نیازها
- Mac با macOS 13.0+
- Xcode 15.0+
- CocoaPods (`sudo gem install cocoapods`)
- Flutter SDK 3.22+

## مراحل راه‌اندازی روی Mac

### ۱. ایجاد پلتفرم iOS
```bash
cd /path/to/saas-platform/mobile
flutter create --platforms=ios .
```

### ۲. نصب Pods
```bash
cd ios
pod install
cd ..
```

### ۳. اعمال تنظیمات Info.plist
فایل `ios_setup/Info.plist.additions` را در `ios/Runner/Info.plist` ادغام کنید.

### ۴. اعمال تنظیمات Podfile
محتوای `ios_setup/Podfile.patch` را در `ios/Podfile` اعمال کنید.

### ۵. Build
```bash
flutter build ios --release
# یا برای Simulator:
flutter run -d "iPhone 15"
```

---

## تنظیمات Signing (App Store)
1. در Xcode پروژه `ios/Runner.xcworkspace` را باز کنید
2. در `Signing & Capabilities` → Team را انتخاب کنید
3. Bundle ID را روی `com.yourcompany.saasPlatformClient` تنظیم کنید

## App Store Connect
1. Archive: `flutter build ipa`
2. آپلود با Xcode Organizer یا `xcrun altool`
