/** Alpine factory: coordinator app shell (sidebar, fullscreen, workspace focus). */
export function qsCoordinatorShell() {
    let collapsedInit = false;
    try {
        collapsedInit = localStorage.getItem('qs.sidebar.coordinator') === '1';
    } catch {
        /* ignore */
    }

    let workspaceFocusInit = false;
    try {
        workspaceFocusInit = localStorage.getItem('qs.coordinator.workspaceFocus') === '1';
    } catch {
        /* ignore */
    }

    const doc = typeof document !== 'undefined' ? document : null;

    return {
        drawerOpen: false,
        collapsed: collapsedInit,
        workspaceFocus: workspaceFocusInit,
        isFullscreen: false,
        fullscreenSupported:
            !!doc &&
            !!(doc.documentElement.requestFullscreen || doc.documentElement.webkitRequestFullscreen),

        syncShellChromeClass() {
            if (!doc) {
                return;
            }
            try {
                doc.documentElement.classList.toggle('qs-shell-sidebar-collapsed', this.collapsed);
                doc.documentElement.classList.toggle('qs-shell-workspace-focus', this.workspaceFocus);
            } catch {
                /* ignore */
            }
        },

        toggleCollapse() {
            this.collapsed = !this.collapsed;
            try {
                localStorage.setItem('qs.sidebar.coordinator', this.collapsed ? '1' : '0');
            } catch {
                /* ignore */
            }
            this.syncShellChromeClass();
        },

        toggleWorkspaceFocus() {
            this.workspaceFocus = !this.workspaceFocus;
            try {
                localStorage.setItem('qs.coordinator.workspaceFocus', this.workspaceFocus ? '1' : '0');
            } catch {
                /* ignore */
            }
            this.syncShellChromeClass();
        },

        async toggleFullscreen() {
            if (!doc) {
                return;
            }
            const el = doc.getElementById('qs-coordinator-root');
            const target = el || doc.documentElement;
            try {
                const fsEl = doc.fullscreenElement || doc.webkitFullscreenElement;
                if (!fsEl) {
                    if (target.requestFullscreen) {
                        await target.requestFullscreen();
                    } else if (target.webkitRequestFullscreen) {
                        await target.webkitRequestFullscreen();
                    }
                } else if (doc.exitFullscreen) {
                    await doc.exitFullscreen();
                } else if (doc.webkitExitFullscreen) {
                    await doc.webkitExitFullscreen();
                }
            } catch {
                /* unsupported or blocked */
            }
            this.syncFullscreen();
        },

        syncFullscreen() {
            if (!doc) {
                return;
            }
            this.isFullscreen = !!(doc.fullscreenElement || doc.webkitFullscreenElement);
        },

        init() {
            if (!doc) {
                return;
            }
            doc.addEventListener('fullscreenchange', () => this.syncFullscreen());
            doc.addEventListener('webkitfullscreenchange', () => this.syncFullscreen());
            this.syncFullscreen();
            this.syncShellChromeClass();
        },
    };
}
