import axios from 'axios';

/**
 * Architecture Review Phase 1 + 4 — split-endpoint hydrator.
 *
 * The /state response now ships only the volatile runtime fields.
 * The invariant exam structure (sections, questions, options) lives at
 * /exam-structure (browser-cacheable, ETag-able) and the saved-answer
 * map lives at /answers (revision-aware, ETag-able).
 *
 * This helper provides a single function that the existing runtime
 * controllers can call in place of `axios.get('/state')` and receive
 * a payload shaped exactly like the legacy combined response — so the
 * deep rendering logic in studentExamRuntime.js / studentExamArena.js
 * does not need to change. The first hydrate makes 3 HTTP calls; every
 * subsequent hydrate makes 1 (state) + 0–2 conditional revalidations
 * that almost always come back 304 with no body.
 *
 * Cache locality is per-session (each call site owns its own
 * hydrator instance via createExamPayloadHydrator(sessionId)).
 */
export function createExamPayloadHydrator(sessionId) {
    let structureCache = null;
    let structureEtag = null;
    let answersCache = null;
    let answersEtag = null;

    const sessionEnc = encodeURIComponent(sessionId);

    function structureUrl() {
        return `/exam-sessions/${sessionEnc}/exam-structure`;
    }
    function answersUrl() {
        return `/exam-sessions/${sessionEnc}/answers`;
    }
    function stateUrl() {
        return `/exam-sessions/${sessionEnc}/state`;
    }

    async function fetchStructure() {
        const headers = {};
        if (structureEtag) {
            headers['If-None-Match'] = structureEtag;
        }
        const r = await axios.get(structureUrl(), {
            headers,
            // 304 is success for our purposes.
            validateStatus: (s) => s === 200 || s === 304,
        });
        if (r.status === 200) {
            structureCache = r.data ?? structureCache;
            const newEtag = r.headers?.etag || r.headers?.ETag;
            if (newEtag) structureEtag = newEtag;
        }
        return structureCache;
    }

    async function fetchAnswers() {
        const headers = {};
        if (answersEtag) {
            headers['If-None-Match'] = answersEtag;
        }
        const r = await axios.get(answersUrl(), {
            headers,
            validateStatus: (s) => s === 200 || s === 304,
        });
        if (r.status === 200) {
            answersCache = r.data ?? answersCache;
            const newEtag = r.headers?.etag || r.headers?.ETag;
            if (newEtag) answersEtag = newEtag;
        }
        return answersCache;
    }

    /**
     * Fetch the current /state and merge in the cached structure + answers
     * so the result has the same shape as the legacy combined endpoint.
     * Use this anywhere the old code did `axios.get('/state').then(({data}) => …)`.
     */
    async function hydrate() {
        // Kick all three off in parallel; the network round-trip cost
        // matches the legacy single /state call when structure / answers
        // are 304-revalidated (which is the steady state).
        const [stateResp, structurePayload, answersPayload] = await Promise.all([
            axios.get(stateUrl()),
            fetchStructure(),
            fetchAnswers(),
        ]);

        const stateData = stateResp.data ?? {};

        // Backward-compat shape: same keys as the legacy /state response.
        // The /state body is already the volatile runtime; we paste in
        // the heavy structural fields from the (locally cached) responses.
        return {
            ...stateData,
            sections: Array.isArray(structurePayload?.sections)
                ? structurePayload.sections
                : [],
            saved_answers: answersPayload?.saved_answers ?? {},
            exam: {
                ...(structurePayload?.exam ?? {}),
                ...(stateData.exam ?? {}),
            },
        };
    }

    /** Force the answers cache to refresh on the next hydrate (e.g. after a save). */
    function invalidateAnswers() {
        answersEtag = null;
    }

    /** Force the structure cache to refresh (rarely needed; examiner edit mid-window). */
    function invalidateStructure() {
        structureEtag = null;
    }

    return {
        hydrate,
        invalidateAnswers,
        invalidateStructure,
    };
}
