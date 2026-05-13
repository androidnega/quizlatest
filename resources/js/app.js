import './bootstrap';

import Alpine from 'alpinejs';
import { qsCoordinatorShell } from './coordinatorShell';
import { ProctoringUploadManager } from './proctoringUploadManager';
import {
    ProctoringRuntimeEngine,
    fetchProctoringCapability,
} from './proctoringRuntimeEngine';
import { ProctoringEventBatcher } from './proctoringEventBatcher';
import { createProctoringEcho } from './proctoringRealtime';
import { ExamStateEngine } from './examStateEngine';

window.Alpine = Alpine;
window.qsCoordinatorShell = qsCoordinatorShell;
window.ProctoringUploadManager = ProctoringUploadManager;
window.ProctoringRuntimeEngine = ProctoringRuntimeEngine;
window.fetchProctoringCapability = fetchProctoringCapability;
window.ProctoringEventBatcher = ProctoringEventBatcher;
window.createProctoringEcho = createProctoringEcho;
window.ExamStateEngine = ExamStateEngine;
window.loadFaceTemplateService = async () => {
    const mod = await import('./faceTemplateService');
    return mod.FaceTemplateService;
};

Alpine.start();
