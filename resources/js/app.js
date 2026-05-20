import '../css/app.css';
import './bootstrap';
import toPersianNumbers from './helpers';
import Alpine from 'alpinejs';
import supportChat from './components/support-chat';

// Alpine.js setup
window.Alpine = Alpine;

// Register components
Alpine.data('supportChat', supportChat);

Alpine.start();

// در دسترس قرار دادن تابع برای استفاده در inline scripts
window.toPersianNumbers = toPersianNumbers;
