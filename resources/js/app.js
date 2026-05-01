import './bootstrap';

import Alpine from 'alpinejs';
import { ProctoringUploadManager } from './proctoringUploadManager';
import { ProctoringRuntimeEngine } from './proctoringRuntimeEngine';
import { FaceTemplateService } from './faceTemplateService';

window.Alpine = Alpine;
window.ProctoringUploadManager = ProctoringUploadManager;
window.ProctoringRuntimeEngine = ProctoringRuntimeEngine;
window.FaceTemplateService = FaceTemplateService;

Alpine.start();
