            <div class="space-y-3">
                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900">{{ __('Live camera') }}</h3>
                                <p class="text-xs font-medium text-slate-500">{{ __('Face framing preview') }}</p>
                            </div>
                            <span id="proctor-camera-live-pill" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ __('Live') }}</span>
                        </div>
                    </div>
                    <div class="p-3 pb-3">
                        <div class="relative aspect-[4/3] overflow-hidden rounded-3xl bg-slate-950">
                            <video id="proctoring-video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay></video>
                            <canvas id="proctoring-face-canvas" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-slate-950/40 lg:hidden" aria-hidden="true">
                                <div class="absolute left-1/2 top-[45%] h-28 w-24 -translate-x-1/2 -translate-y-1/2 rounded-[45%] border border-emerald-400/80"></div>
                            </div>
                            <div class="absolute bottom-3 left-3 right-3 rounded-2xl bg-black/55 px-3 py-2 text-xs font-bold text-white backdrop-blur">
                                <div class="flex items-center justify-between gap-2">
                                    <span><i class="fa-solid fa-eye me-1 text-cyan-300" aria-hidden="true"></i><span id="proctor-eye-line">{{ __('Eyes on screen') }}</span></span>
                                    <span id="proctor-eye-status" class="text-emerald-300">{{ __('Normal') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ __('Face') }}</p>
                                <p id="proctor-face-status" class="mt-0.5 text-sm font-extrabold text-slate-900">—</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ __('Risk score') }}</p>
                                <p id="proctor-risk-score" class="mt-0.5 text-sm font-extrabold text-slate-900">0</p>
                            </div>
                        </div>
                        <p id="proctoring-local-hint" class="mt-2 min-h-[1.25rem] text-xs leading-snug text-slate-500"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                            <i class="fa-solid fa-microphone text-xs"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-xs font-extrabold text-slate-900">{{ __('Microphone') }}</h3>
                                <span id="exam-mic-level-label" class="text-[10px] font-bold text-emerald-700">{{ __('Normal') }}</span>
                            </div>
                            <div id="exam-mic-wave" class="qs-exam-sound-wave mt-1.5 flex h-7 items-end justify-center gap-1 rounded-lg bg-slate-50 px-2 py-1">
                                @foreach (range(1, 7) as $_)
                                    <span class="inline-block w-1.5 rounded-full bg-slate-900"></span>
                                @endforeach
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="shrink-0 text-[10px] font-bold text-slate-500">{{ __('Input level') }}</span>
                                <div class="h-1 min-w-0 flex-1 rounded-full bg-slate-100">
                                    <div id="exam-mic-level-bar" class="h-1 w-[8%] rounded-full bg-slate-900 transition-[width] duration-150"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5">
                    <h3 class="text-sm font-extrabold text-amber-900">{{ __('Invigilation notice') }}</h3>
                    <p class="mt-2 text-xs font-medium leading-relaxed text-amber-800">
                        {{ __('Tab changes, leaving fullscreen, and face visibility may be recorded for review when enabled by your school.') }}
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-amber-900/90">
                        {{ __('Camera-based phone detection is probabilistic and not guaranteed; it is used for warnings and review, not as proof of misconduct on its own.') }}
                    </p>
                </div>
            </div>
