import '../css/app.css';
import './bootstrap';
import toPersianNumbers from './helpers';
import Alpine from 'alpinejs';

// Alpine.js setup
window.Alpine = Alpine;
Alpine.start();

// در دسترس قرار دادن تابع برای استفاده در inline scripts
window.toPersianNumbers = toPersianNumbers;
