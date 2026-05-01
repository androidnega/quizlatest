import './bootstrap';

import Alpine from 'alpinejs';
import { ProctoringUploadManager } from './proctoringUploadManager';
import {
    ProctoringRuntimeEngine,
    fetchProctoringCapability,
} from './proctoringRuntimeEngine';
import { FaceTemplateService } from './faceTemplateService';
import { ProctoringEventBatcher } from './proctoringEventBatcher';
import { createProctoringEcho } from './proctoringRealtime';
import { ExamStateEngine } from './examStateEngine';

window.Alpine = Alpine;
window.ProctoringUploadManager = ProctoringUploadManager;
window.ProctoringRuntimeEngine = ProctoringRuntimeEngine;
window.fetchProctoringCapability = fetchProctoringCapability;
window.FaceTemplateService = FaceTemplateService;
window.ProctoringEventBatcher = ProctoringEventBatcher;
window.createProctoringEcho = createProctoringEcho;
window.ExamStateEngine = ExamStateEngine;

Alpine.start();
