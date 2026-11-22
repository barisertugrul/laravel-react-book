import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function FormField({
    name,
    label,
    error,
    ...props
}: { name: string; label: string; error?: string } & ComponentProps<'input'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} {...props} />
            <InputError message={error} />
        </div>
    );
}

export const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30';
