import { useEffect, useRef, useState } from 'react';
import type { Category } from '@/types/catalog';

type Props = {
    categories: Category[];
};

export function CategoryNav({ categories }: Props) {
    const [activeId, setActiveId] = useState<number | null>(
        categories[0]?.id ?? null,
    );
    const scrollerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const sections = categories
            .map((c) => document.getElementById(`category-${c.id}`))
            .filter((el): el is HTMLElement => el !== null);

        if (sections.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((e) => e.isIntersecting)
                    .sort(
                        (a, b) =>
                            a.boundingClientRect.top - b.boundingClientRect.top,
                    );

                if (visible[0]) {
                    const id = Number(
                        visible[0].target.id.replace('category-', ''),
                    );
                    setActiveId(id);
                }
            },
            {
                rootMargin: '-120px 0px -60% 0px',
                threshold: 0,
            },
        );

        for (const section of sections) {
            observer.observe(section);
        }

        return () => observer.disconnect();
    }, [categories]);

    // Keep the active pill in view inside the horizontally scrolling nav. The
    // scrollbar is hidden, so without this the trailing categories would be
    // unreachable with a mouse and the nav wouldn't follow the page scroll.
    useEffect(() => {
        if (activeId === null) {
            return;
        }

        const scroller = scrollerRef.current;

        if (!scroller) {
            return;
        }

        const button = scroller.querySelector<HTMLElement>(
            `[data-category-id="${activeId}"]`,
        );

        if (!button) {
            return;
        }

        const scrollerRect = scroller.getBoundingClientRect();
        const buttonRect = button.getBoundingClientRect();
        const delta =
            buttonRect.left -
            scrollerRect.left -
            (scroller.clientWidth - button.clientWidth) / 2;

        scroller.scrollTo({
            left: scroller.scrollLeft + delta,
            behavior: 'smooth',
        });
    }, [activeId]);

    function scrollTo(id: number) {
        const el = document.getElementById(`category-${id}`);

        if (!el) {
            return;
        }

        const offset = 128;
        const top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    }

    return (
        <nav className="sticky top-16 z-20 border-b border-stone-200/80 bg-white/85 backdrop-blur-md">
            <div className="mx-auto max-w-6xl px-2 sm:px-4">
                <div
                    ref={scrollerRef}
                    className="flex [scrollbar-width:none] scrollbar-none gap-1 overflow-x-auto py-3 [-ms-overflow-style:none]"
                >
                    {categories.map((category) => {
                        const isActive = category.id === activeId;

                        return (
                            <button
                                key={category.id}
                                type="button"
                                data-category-id={category.id}
                                onClick={() => scrollTo(category.id)}
                                className={`shrink-0 rounded-full px-4 py-2 text-sm font-medium transition ${
                                    isActive
                                        ? 'bg-stone-900 text-white'
                                        : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                }`}
                            >
                                {category.name}
                            </button>
                        );
                    })}
                </div>
            </div>
        </nav>
    );
}
