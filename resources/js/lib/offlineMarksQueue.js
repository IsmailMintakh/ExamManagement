/**
 * Offline marks-entry queue.
 *
 * The marks-entry page autosaves to POST /marks/{exam}/autosave every couple
 * of seconds while online. When the network drops, we fall back to writing
 * the full payload snapshot to IndexedDB keyed by (exam, subject, section).
 * One snapshot per scope = newest wins, no accumulation of stale rows.
 *
 * On the `online` event (or manual "Sync now"), the page calls drainAll()
 * which POSTs every pending snapshot in turn and clears the rows on success.
 *
 * Design notes:
 *   - We only queue what the server's autosave already accepts: same shape,
 *     same endpoint. No special server-side code needed.
 *   - Snapshots include CSRF tokens are NOT cached — we always read the
 *     current token at flush time so they don't expire.
 *   - DB name + version bumped here when schema changes; old DBs auto-upgrade.
 */

const DB_NAME = 'exammgmt-offline'
const DB_VERSION = 1
const STORE = 'marks_snapshots'

/** Open the IDB connection, lazily creating the object store. */
function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION)
        req.onupgradeneeded = (event) => {
            const db = event.target.result
            if (!db.objectStoreNames.contains(STORE)) {
                // Composite key: exam_id|subject_id|section_id
                db.createObjectStore(STORE, { keyPath: 'scope_key' })
            }
        }
        req.onsuccess = () => resolve(req.result)
        req.onerror = () => reject(req.error)
    })
}

function scopeKey(examId, subjectId, sectionId) {
    return `${examId}|${subjectId}|${sectionId}`
}

/** Save (overwrite) the latest snapshot for a given scope. */
export async function queueSnapshot({ examId, subjectId, sectionId, payload }) {
    const db = await openDb()
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite')
        const store = tx.objectStore(STORE)
        const record = {
            scope_key: scopeKey(examId, subjectId, sectionId),
            exam_id: examId,
            subject_id: subjectId,
            section_id: sectionId,
            payload, // { subject_id, section_id, marks: [...] }
            queued_at: new Date().toISOString(),
        }
        const putReq = store.put(record)
        putReq.onsuccess = () => resolve(record)
        putReq.onerror = () => reject(putReq.error)
        tx.oncomplete = () => db.close()
    })
}

/** Get the snapshot for a given scope, or null. */
export async function getSnapshot({ examId, subjectId, sectionId }) {
    const db = await openDb()
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly')
        const store = tx.objectStore(STORE)
        const req = store.get(scopeKey(examId, subjectId, sectionId))
        req.onsuccess = () => resolve(req.result || null)
        req.onerror = () => reject(req.error)
        tx.oncomplete = () => db.close()
    })
}

/** Remove a queued snapshot once it's been delivered. */
export async function deleteSnapshot({ examId, subjectId, sectionId }) {
    const db = await openDb()
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite')
        const store = tx.objectStore(STORE)
        const req = store.delete(scopeKey(examId, subjectId, sectionId))
        req.onsuccess = () => resolve()
        req.onerror = () => reject(req.error)
        tx.oncomplete = () => db.close()
    })
}

/** Return all queued snapshots — used by the bulk drain. */
export async function listAllSnapshots() {
    const db = await openDb()
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly')
        const store = tx.objectStore(STORE)
        const req = store.getAll()
        req.onsuccess = () => resolve(req.result || [])
        req.onerror = () => reject(req.error)
        tx.oncomplete = () => db.close()
    })
}

/**
 * Try to POST every queued snapshot. Returns { sent, failed } counts.
 * Caller is responsible for triggering this — typically on `online` event
 * and on page load.
 *
 * `endpointFor(examId)` lets the caller produce the autosave URL for each
 * scope (since URLs may include the exam id segment).
 */
export async function drainAll(endpointFor) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

    const items = await listAllSnapshots()
    let sent = 0, failed = 0
    for (const item of items) {
        try {
            const res = await fetch(endpointFor(item.exam_id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(item.payload),
            })
            if (res.ok) {
                await deleteSnapshot({
                    examId: item.exam_id,
                    subjectId: item.subject_id,
                    sectionId: item.section_id,
                })
                sent++
            } else {
                failed++
            }
        } catch (e) {
            failed++ // still offline / endpoint unreachable
        }
    }
    return { sent, failed }
}
