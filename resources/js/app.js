import '../css/app.css';
import './bootstrap';
import toPersianNumbers from './helpers';
import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
import Sortable from 'sortablejs';
import supportChat from './components/support-chat';
import usersIndex from './components/users-index';

// Alpine.js setup
window.Alpine = Alpine;
window.Sortable = Sortable;

// Plugins
Alpine.plugin(Collapse);

// Register components
Alpine.data('supportChat', supportChat);
Alpine.data('usersIndex', usersIndex);

Alpine.start();

// در دسترس قرار دادن تابع برای استفاده در inline scripts
window.toPersianNumbers = toPersianNumbers;
