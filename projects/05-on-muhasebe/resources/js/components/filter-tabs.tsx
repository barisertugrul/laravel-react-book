import { cn } from '@/lib/utils';

export default function FilterTabs<T extends string>({
    value,
    options,
    onChange,
}: {
    value?: T;
    options: { value?: T; label: string }[];
    onChange: (v?: T) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1">
            {options.map((o) => (
                <button
                    key={o.label}
                    type="button"
                    onClick={() => onChange(o.value)}
                    className={cn(
                        'rounded-full border px-3 py-1 text-sm transition',
                        value === o.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent',
                    )}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}
