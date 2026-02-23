import InputError from '@/Components/InputError';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {

    const user = usePage().props.auth.user;

    const [open, setOpen] = useState (false);
    const [date, setDate] = useState(undefined);

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone,
        date_of_birth: user.date_of_birth,
        gender: user.gender,
        address: user.address,
    });

    const onHandleChange = (e) => setData(e.target.name, e.target.value);

    const onHandleSubmit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>Profile Information</CardTitle>

                <CardDescription>Update your account's profile information and email address.</CardDescription>
            </CardHeader>

            <CardContent>
                <form onSubmit={onHandleSubmit} className="mt-6 space-y-6">
                    <div>
                        <Label htmlFor="name">Nama</Label>

                        <Input id="name" name="name" value={data.name} onChange={onHandleChange} autoComplete="name" />

                        {errors.name && <InputError className="mt-2" message={errors.name} />}
                    </div>

                    <div>
                        <Label htmlFor="email">Email</Label>

                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={onHandleChange}
                            autoComplete="username"
                        />

                        {errors.name && <InputError className="mt-2" message={errors.email} />}
                    </div>

                    <div>
                        <Label htmlFor="phone">Nomor Handphone</Label>
                        <Input
                            name="phone"
                            id="phone"
                            type="text"
                            placeholder="Masukkan nomor handphone..."
                            value={data.phone}
                            onChange={onHandleChange}
                        />
                        {errors.phone && <InputError message={errors.phone} />}
                    </div>

                    <div className="flex flex-col gap-3">
                        <Label htmlFor='date_of_birth'>Tanggal Lahir</Label>
                        <Popover open={open} onOpenChange={setOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    id="date"
                                    className="w-48 justify-between font-normal"
                                    >
                                    {date ? date.toLocaleDateString() : "Select date"}
                                    <ChevronDownIcon/>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={date}
                                    captionLayout="dropdown"
                                    onSelect={(date) => {
                                    setData('date_of_birth', date.toISOString().split('T')[0]);
                                    setDate(date);
                                    setOpen(false);
                                    }}
                                />
                            </PopoverContent>
                        </Popover>
                    </div>

                    <div className="grid w-full items-center gap-1.5">
                        <Label htmlFor="address">Alamat</Label>
                        <Textarea
                            name="address"
                            id="address"
                            type="text"
                            placeholder="Masukkan alamat..."
                            value={data.address}
                            onChange={onHandleChange}
                        />
                        {errors.address && <InputError message={errors.address} />}
                    </div>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div>
                            <p className="mt-2 text-sm text-foreground">
                                Your email address is unverified.
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="rounded-md text-sm text-muted-foreground underline hover:text-foreground focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>

                            {status === 'verification-link-sent' && (
                                <Alert variant="success">
                                    <AlertDescription>
                                        A new verification link has been sent to your email address.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button variant="orange" size="lg" disabled={processing}>
                            Save
                        </Button>

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
