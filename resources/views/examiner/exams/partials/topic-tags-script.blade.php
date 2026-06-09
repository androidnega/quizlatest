<script>
    function splitCommaSeparatedRespectingQuotes(str) {
        const s = String(str || '').trim();
        if (!s) {
            return [];
        }
        const parts = [];
        let cur = '';
        let inD = false;
        let inS = false;
        for (let i = 0; i < s.length; i++) {
            const c = s[i];
            const prev = i > 0 ? s[i - 1] : '';
            if (inD) {
                if (c === '"' && prev !== '\\') {
                    inD = false;
                } else {
                    cur += c;
                }
                continue;
            }
            if (inS) {
                if (c === "'" && prev !== '\\') {
                    inS = false;
                } else {
                    cur += c;
                }
                continue;
            }
            if (c === '"') {
                inD = true;
                continue;
            }
            if (c === "'") {
                inS = true;
                continue;
            }
            if (c === ',') {
                if (cur.trim()) {
                    parts.push(cur.trim());
                }
                cur = '';
                continue;
            }
            cur += c;
        }
        if (cur.trim()) {
            parts.push(cur.trim());
        }
        return parts.filter((p) => p.length > 0);
    }

    /** First topic before an unquoted comma; rest is text after that comma (for live chip UX). */
    function takeFirstCommaSegmentOutsideQuotes(str) {
        const s = String(str);
        let cur = '';
        let inD = false;
        let inS = false;
        for (let i = 0; i < s.length; i++) {
            const c = s[i];
            const prev = i > 0 ? s[i - 1] : '';
            if (inD) {
                if (c === '"' && prev !== '\\') {
                    inD = false;
                } else {
                    cur += c;
                }
                continue;
            }
            if (inS) {
                if (c === "'" && prev !== '\\') {
                    inS = false;
                } else {
                    cur += c;
                }
                continue;
            }
            if (c === '"') {
                inD = true;
                continue;
            }
            if (c === "'") {
                inS = true;
                continue;
            }
            if (c === ',') {
                return { first: cur.trim(), rest: s.slice(i + 1) };
            }
            cur += c;
        }
        return { first: null, rest: s };
    }

    function topicTags(initial) {
        function parseInitial(raw) {
            if (raw == null) {
                return [];
            }
            const s = String(raw).trim();
            if (!s) {
                return [];
            }
            if (s.startsWith('[')) {
                try {
                    const j = JSON.parse(s);
                    if (Array.isArray(j)) {
                        return [
                            ...new Set(
                                j.filter((x) => typeof x === 'string').map((t) => t.trim()).filter((t) => t.length > 0),
                            ),
                        ];
                    }
                } catch (e) {}
            }
            return [...new Set(splitCommaSeparatedRespectingQuotes(s))];
        }
        return {
            tags: parseInitial(initial),
            input: '',
            topicChipClass(idx) {
                const palettes = [
                    'border-qs-primary/35 bg-qs-primary/10 text-qs-primary',
                    'border-emerald-400/45 bg-emerald-50 text-emerald-900',
                    'border-violet-400/45 bg-violet-50 text-violet-900',
                    'border-amber-400/45 bg-amber-50 text-amber-950',
                    'border-sky-400/45 bg-sky-50 text-sky-950',
                ];
                return palettes[idx % palettes.length] + ' px-2 py-0.5';
            },
            topicChipCloseClass(idx) {
                const muted = [
                    'text-qs-primary/80',
                    'text-emerald-900/70',
                    'text-violet-900/70',
                    'text-amber-950/70',
                    'text-sky-950/70',
                ];
                return muted[idx % muted.length];
            },
            get joined() {
                return JSON.stringify(this.tags);
            },
            commitOneSegmentOnComma(e) {
                e.preventDefault();
                const el = e.target;
                const raw = String(this.input || '');
                const start = typeof el.selectionStart === 'number' ? el.selectionStart : raw.length;
                const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
                const synthetic = raw.slice(0, start) + ',' + raw.slice(end);
                const { first, rest } = takeFirstCommaSegmentOutsideQuotes(synthetic);
                if (first !== null) {
                    if (first !== '' && !this.tags.includes(first)) {
                        this.tags.push(first);
                    }
                    this.input = rest.replace(/^\s+/, '');
                } else {
                    this.input = synthetic;
                }
                this.$nextTick(() => {
                    try {
                        const pos = this.input.length;
                        el.setSelectionRange(pos, pos);
                    } catch (_) {}
                });
            },
            addFromInput() {
                const v = String(this.input || '').trim();
                if (v === '') {
                    return;
                }
                const parts = splitCommaSeparatedRespectingQuotes(v);
                parts.forEach((p) => {
                    if (p && !this.tags.includes(p)) {
                        this.tags.push(p);
                    }
                });
                this.input = '';
            },
            remove(idx) {
                this.tags.splice(idx, 1);
            },
        };
    }
</script>
