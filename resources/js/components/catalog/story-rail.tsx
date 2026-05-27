import { useState } from 'react';
import type { StoryGroup } from '@/types/catalog';
import { StoryViewer } from './story-viewer';

type Props = {
    groups: StoryGroup[];
};

export function StoryRail({ groups }: Props) {
    const [activeIndex, setActiveIndex] = useState<number | null>(null);

    if (groups.length === 0) {
        return null;
    }

    return (
        <>
            <div className="mb-6 -mx-4 sm:-mx-6">
                <div className="flex gap-3 overflow-x-auto px-4 pb-1 sm:px-6 scrollbar-none [scrollbar-width:none] [-ms-overflow-style:none]">
                    {groups.map((group, index) => (
                        <button
                            key={group.id}
                            type="button"
                            onClick={() => setActiveIndex(index)}
                            className="group flex w-20 shrink-0 flex-col items-center gap-1.5 focus:outline-none"
                        >
                            <span className="relative grid size-20 place-items-center rounded-full bg-gradient-to-tr from-amber-400 via-orange-500 to-rose-500 p-[2.5px] transition group-hover:scale-105 group-active:scale-95">
                                <span className="grid size-full place-items-center rounded-full bg-white p-[2px]">
                                    {group.preview_url ? (
                                        <img
                                            src={group.preview_url}
                                            alt={group.title}
                                            className="size-full rounded-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <span className="grid size-full place-items-center rounded-full bg-stone-100 text-xs font-semibold uppercase text-stone-500">
                                            {group.title.slice(0, 2)}
                                        </span>
                                    )}
                                </span>
                            </span>
                            <span className="line-clamp-2 max-w-full text-center text-xs font-medium leading-tight text-stone-700">
                                {group.title}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            {activeIndex !== null && (
                <StoryViewer
                    groups={groups}
                    startGroupIndex={activeIndex}
                    onClose={() => setActiveIndex(null)}
                />
            )}
        </>
    );
}
