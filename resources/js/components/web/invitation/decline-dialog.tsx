import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type DeclineDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Where to post; null in preview (nothing is sent). */
    url: string | null;
    onDeclined?: () => void;
};

/**
 * Confirms a decline before it is sent, with an optional note to the host.
 * Portals to <body>, outside the invitation's shadow root.
 */
export function DeclineDialog({
    open,
    onOpenChange,
    url,
    onDeclined,
}: DeclineDialogProps) {
    const form = useForm({ attendance: 'declined', note: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (!url) {
            return;
        }

        form.post(url, {
            preserveScroll: true,
            onSuccess: (page) => {
                // An expired link comes back as a flash error.
                if (!page.props.flash.error) {
                    onOpenChange(false);
                    form.reset();
                    onDeclined?.();
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Can't make it?</DialogTitle>
                    <DialogDescription>
                        We'll let the host know you won't be attending.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 p-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="decline-note">
                            Note to the host (optional)
                        </Label>
                        <Textarea
                            id="decline-note"
                            rows={3}
                            maxLength={500}
                            placeholder="e.g. Sorry, I'll be travelling that week."
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                        {form.errors.note && (
                            <p className="text-xs text-destructive">
                                {form.errors.note}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center justify-end gap-2">
                        {!url && (
                            <p className="mr-auto text-xs text-muted-foreground">
                                Preview: nothing is sent.
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Go back
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={!url || form.processing}
                        >
                            {form.processing ? 'Sending…' : 'Decline'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
