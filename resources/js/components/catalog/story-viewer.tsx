import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { StoryGroup } from '@/types/catalog';

type Props = {
    groups: StoryGroup[];
    startGroupIndex: number;
    onClose: () => void;
};

const FALLBACK_PHOTO_MS = 5000;
const TICK_MS = 50;

export function StoryViewer({ groups, startGroupIndex, onClose }: Props) {
    const [groupIndex, setGroupIndex] = useState(startGroupIndex);
    const [storyIndex, setStoryIndex] = useState(0);
    const [progress, setProgress] = useState(0);
    const [paused, setPaused] = useState(false);

    const group = groups[groupIndex];
    const story = group?.stories[storyIndex];
    const isVideo = story?.media_type === 'video';

    const videoRef = useRef<HTMLVideoElement | null>(null);
    const tickRef = useRef<number | null>(null);

    const durationMs = useMemo(() => {
        if (!story) return FALLBACK_PHOTO_MS;
        return story.duration_ms || FALLBACK_PHOTO_MS;
    }, [story]);

    const goNext = useCallback(() => {
        setProgress(0);
        if (!group) return;
        if (storyIndex < group.stories.length - 1) {
            setStoryIndex((i) => i + 1);
            return;
        }
        if (groupIndex < groups.length - 1) {
            setGroupIndex((i) => i + 1);
            setStoryIndex(0);
            return;
        }
        onClose();
    }, [group, storyIndex, groupIndex, groups.length, onClose]);

    const goPrev = useCallback(() => {
        setProgress(0);
        if (storyIndex > 0) {
            setStoryIndex((i) => i - 1);
            return;
        }
        if (groupIndex > 0) {
            const prev = groups[groupIndex - 1];
            setGroupIndex((i) => i - 1);
            setStoryIndex(Math.max(0, (prev?.stories.length ?? 1) - 1));
        }
    }, [storyIndex, groupIndex, groups]);

    // Photo timer: advance progress on a fixed tick. Videos drive their own
    // progress from the timeupdate event below.
    useEffect(() => {
        if (!story || isVideo || paused) return;

        tickRef.current = window.setInterval(() => {
            setProgress((p) => {
                const next = p + (TICK_MS / durationMs) * 100;
                if (next >= 100) {
                    return 100;
                }
                return next;
            });
        }, TICK_MS);

        return () => {
            if (tickRef.current !== null) {
                window.clearInterval(tickRef.current);
                tickRef.current = null;
            }
        };
    }, [story, isVideo, paused, durationMs, storyIndex, groupIndex]);

    // Auto-advance once a photo reaches 100%.
    useEffect(() => {
        if (progress >= 100 && !isVideo) {
            goNext();
        }
    }, [progress, isVideo, goNext]);

    // Reset progress when entering a new story.
    useEffect(() => {
        setProgress(0);
    }, [groupIndex, storyIndex]);

    // Sync video play/pause state.
    useEffect(() => {
        const el = videoRef.current;
        if (!el) return;
        if (paused) {
            el.pause();
        } else {
            el.play().catch(() => {
                /* autoplay rejected — user gesture required, will recover on tap */
            });
        }
    }, [paused, storyIndex, groupIndex]);

    // Keyboard navigation.
    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowRight') goNext();
            if (e.key === 'ArrowLeft') goPrev();
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose, goNext, goPrev]);

    // Touch swipe between groups.
    const touchRef = useRef<{ x: number; y: number; t: number } | null>(null);
    function onTouchStart(e: React.TouchEvent) {
        const t = e.touches[0];
        touchRef.current = { x: t.clientX, y: t.clientY, t: Date.now() };
        setPaused(true);
    }
    function onTouchEnd(e: React.TouchEvent) {
        setPaused(false);
        const start = touchRef.current;
        touchRef.current = null;
        if (!start) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - start.x;
        const dy = t.clientY - start.y;
        const dt = Date.now() - start.t;
        if (Math.abs(dy) > Math.abs(dx) && dy > 80) {
            onClose();
            return;
        }
        if (Math.abs(dx) > 60 && dt < 500) {
            if (dx < 0) {
                // swipe left → next group
                setProgress(0);
                if (groupIndex < groups.length - 1) {
                    setGroupIndex((i) => i + 1);
                    setStoryIndex(0);
                } else {
                    onClose();
                }
            } else {
                // swipe right → prev group
                setProgress(0);
                if (groupIndex > 0) {
                    setGroupIndex((i) => i - 1);
                    setStoryIndex(0);
                }
            }
        }
    }

    if (!group || !story) return null;

    function onTapZone(side: 'left' | 'right') {
        if (side === 'left') goPrev();
        else goNext();
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/95 select-none"
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <button
                type="button"
                onClick={onClose}
                className="absolute right-4 top-4 z-30 grid size-10 place-items-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
                aria-label="Закрыть"
            >
                <X className="size-5" />
            </button>

            <button
                type="button"
                onClick={goPrev}
                className="absolute left-2 top-1/2 z-20 hidden -translate-y-1/2 grid size-12 place-items-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 sm:grid"
                aria-label="Назад"
                disabled={groupIndex === 0 && storyIndex === 0}
            >
                <ChevronLeft className="size-6" />
            </button>
            <button
                type="button"
                onClick={goNext}
                className="absolute right-2 top-1/2 z-20 hidden -translate-y-1/2 grid size-12 place-items-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 sm:grid"
                aria-label="Вперёд"
            >
                <ChevronRight className="size-6" />
            </button>

            <div className="relative flex h-full w-full max-w-[420px] flex-col sm:h-[90vh] sm:rounded-2xl sm:overflow-hidden">
                <div className="absolute left-0 right-0 top-0 z-10 flex gap-1 px-3 pt-3">
                    {group.stories.map((_, i) => (
                        <div
                            key={i}
                            className="h-0.5 flex-1 overflow-hidden rounded-full bg-white/30"
                        >
                            <div
                                className="h-full bg-white transition-[width] duration-75 ease-linear"
                                style={{
                                    width: `${
                                        i < storyIndex
                                            ? 100
                                            : i === storyIndex
                                              ? progress
                                              : 0
                                    }%`,
                                }}
                            />
                        </div>
                    ))}
                </div>

                <div className="absolute left-0 right-0 top-5 z-10 flex items-center gap-3 px-4 pt-3 text-white">
                    {group.preview_url ? (
                        <img
                            src={group.preview_url}
                            alt=""
                            className="size-8 rounded-full object-cover ring-1 ring-white/40"
                        />
                    ) : (
                        <span className="grid size-8 place-items-center rounded-full bg-white/20 text-[10px] font-semibold uppercase">
                            {group.title.slice(0, 2)}
                        </span>
                    )}
                    <span className="text-sm font-semibold drop-shadow-sm">{group.title}</span>
                </div>

                <div
                    className="relative flex-1 bg-stone-900"
                    onMouseDown={() => setPaused(true)}
                    onMouseUp={() => setPaused(false)}
                    onMouseLeave={() => setPaused(false)}
                >
                    {isVideo ? (
                        <video
                            key={story.id}
                            ref={videoRef}
                            src={story.media_url ?? undefined}
                            className="size-full object-cover"
                            autoPlay
                            playsInline
                            muted
                            onTimeUpdate={(e) => {
                                const el = e.currentTarget;
                                if (el.duration > 0) {
                                    setProgress((el.currentTime / el.duration) * 100);
                                }
                            }}
                            onEnded={goNext}
                        />
                    ) : (
                        <img
                            key={story.id}
                            src={story.media_url ?? undefined}
                            alt=""
                            className="size-full"
                        />
                    )}

                    <button
                        type="button"
                        aria-label="Предыдущий"
                        className="absolute left-0 top-0 z-10 h-full w-1/3"
                        onClick={() => onTapZone('left')}
                    />
                    <button
                        type="button"
                        aria-label="Следующий"
                        className="absolute right-0 top-0 z-10 h-full w-1/3"
                        onClick={() => onTapZone('right')}
                    />

                    {story.cta_url && story.cta_label && (
                        <a
                            href={story.cta_url}
                            className="absolute inset-x-0 bottom-8 z-20 mx-auto w-fit max-w-[80%] rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-stone-900 shadow-lg transition hover:bg-stone-100"
                        >
                            {story.cta_label}
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}
