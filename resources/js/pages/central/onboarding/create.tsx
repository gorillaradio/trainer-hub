import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/input-error';
import type { FormEvent } from 'react';

function slugify(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-');
}

export default function OnboardingCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const slug = slugify(data.name);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/onboarding');
    }

    return (
        <>
            <Head title="Crea la tua organizzazione" />
            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">Crea la tua organizzazione</CardTitle>
                        <CardDescription>
                            Inserisci il nome della tua palestra o centro sportivo per iniziare.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome organizzazione</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                    placeholder="Es. Palestra Gorilla"
                                />
                                <InputError message={errors.name} />
                            </div>

                            {slug && (
                                <p className="text-sm text-muted-foreground">
                                    Il tuo indirizzo sarà: <span className="font-medium text-foreground">/app/{slug}/</span>
                                </p>
                            )}

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing && <Spinner />}
                                Crea organizzazione
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
