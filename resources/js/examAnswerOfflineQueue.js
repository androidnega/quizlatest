/**
 * IndexedDB queue for exam answers when offline or after failed saves.
 * One row per question (latest revision wins); never overwrites a higher revision.
 */

const DB_NAME = 'quizsnap-exam-runtime';
const DB_VERSION = 1;
const STORE = 'pendingAnswers';

/** @type {Promise<IDBDatabase> | null} */
let dbPromise = null;

function openDb() {
    if (dbPromise) {
        return dbPromise;
    }
    dbPromise = new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => {
            dbPromise = null;
            reject(req.error);
        };
    });
    return dbPromise;
}

function storeKey(sessionId, questionId) {
    return `${sessionId}:${questionId}`;
}

/**
 * @param {string} sessionId
 * @param {number} questionId
 * @param {number} revision
 * @param {object} payload
 */
export async function queuePendingAnswer(sessionId, questionId, revision, payload) {
    try {
        const db = await openDb();
        const key = storeKey(sessionId, questionId);
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            const os = tx.objectStore(STORE);
            const g = os.get(key);
            g.onsuccess = () => {
                const existing = g.result;
                if (existing && existing.revision > revision) {
                    return;
                }
                os.put({
                    key,
                    sessionId,
                    questionId,
                    revision,
                    payload,
                    updatedAt: Date.now(),
                });
            };
            g.onerror = () => reject(g.error);
        });
    } catch {
        //
    }
}

/**
 * @param {string} sessionId
 * @param {number} questionId
 */
export async function clearPendingAnswer(sessionId, questionId) {
    try {
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.objectStore(STORE).delete(storeKey(sessionId, questionId));
        });
    } catch {
        //
    }
}

/**
 * @param {string} sessionId
 * @returns {Promise<Array<{ questionId: number, revision: number, payload: object }>>}
 */
export async function listPendingForSession(sessionId) {
    try {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readonly');
        const os = tx.objectStore(STORE);
        const rows = await new Promise((resolve, reject) => {
            const out = [];
            const cur = os.openCursor();
            cur.onsuccess = () => {
                const c = cur.result;
                if (!c) {
                    resolve(out);
                    return;
                }
                const v = c.value;
                if (v.sessionId === sessionId) {
                    out.push({
                        questionId: v.questionId,
                        revision: v.revision,
                        payload: v.payload,
                    });
                }
                c.continue();
            };
            cur.onerror = () => reject(cur.error);
        });
        return rows;
    } catch {
        return [];
    }
}
