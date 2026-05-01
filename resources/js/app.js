import './bootstrap';

import Alpine from 'alpinejs';
import { ProctoringUploadManager } from './proctoringUploadManager';

window.Alpine = Alpine;
window.ProctoringUploadManager = ProctoringUploadManager;

Alpine.start();
