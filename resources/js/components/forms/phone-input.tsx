import { useEffect, useState } from 'react';
import { formatPhoneMask, isValidPhone, normalizePhone } from '@/lib/phone';

type Props = {
    value: string;
    onChange: (canonical: string) => void;
    error?: string | null;
    label?: string;
    required?: boolean;
    placeholder?: string;
    autoFocus?: boolean;
    disabled?: boolean;
    /**
     * Optional id/name so the parent can hook into the field (e.g. for
     * `htmlFor` labels). Defaults to "phone".
     */
    name?: string;
};

/**
 * Masked phone input. Always shows +7 (XXX) XXX-XX-XX while the user types.
 * Propagates the canonical +7XXXXXXXXXX value (or whatever raw digits were
 * entered if not yet complete) to the parent via onChange — backend
 * validation is still authoritative.
 */
export function PhoneInput({
    value,
    onChange,
    error,
    label = 'Телефон',
    required,
    placeholder = '+7 (999) 123-45-67',
    autoFocus,
    disabled,
    name = 'phone',
}: Props) {
    const [display, setDisplay] = useState(() => formatPhoneMask(value));

    // Keep the masked display in sync when the canonical value changes from
    // outside (e.g. when the page hydrates with a customer's saved phone).
    useEffect(() => {
        const masked = formatPhoneMask(value);
        setDisplay((prev) => (prev === masked ? prev : masked));
    }, [value]);

    function handleChange(raw: string) {
        const masked = formatPhoneMask(raw);
        setDisplay(masked);
        const normalized = normalizePhone(masked);
        // While incomplete we still propagate what we have so the parent
        // form's "controlled" state stays consistent — backend validation
        // catches incomplete input on submit.
        onChange(normalized ?? raw.replace(/\s+/g, ''));
    }

    const showError = Boolean(error);
    const isComplete = display.length > 0 && isValidPhone(display);

    return (
        <div>
            {label && (
                <label htmlFor={name} className="mb-1.5 block text-xs font-medium text-stone-700">
                    {label}
                    {required && <span className="ml-0.5 text-rose-600">*</span>}
                </label>
            )}
            <input
                id={name}
                name={name}
                type="tel"
                inputMode="tel"
                autoComplete="tel"
                value={display}
                onChange={(e) => handleChange(e.target.value)}
                onPaste={(e) => {
                    const text = e.clipboardData.getData('text');
                    if (text) {
                        e.preventDefault();
                        handleChange(text);
                    }
                }}
                placeholder={placeholder}
                autoFocus={autoFocus}
                disabled={disabled}
                aria-invalid={showError || undefined}
                className={`w-full rounded-xl border bg-white px-3 py-2.5 text-base text-stone-900 placeholder:text-stone-400 focus:outline-none focus:ring-2 disabled:bg-stone-50 disabled:text-stone-500 ${
                    showError
                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                        : isComplete
                          ? 'border-emerald-300 focus:border-emerald-400 focus:ring-emerald-100'
                          : 'border-stone-200 focus:border-amber-400 focus:ring-amber-100'
                }`}
            />
            {showError && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}
