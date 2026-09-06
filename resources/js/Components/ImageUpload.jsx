import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

const WIDTH = 1200;
const HEIGHT = 900;
const MAX_OUTPUT_BYTES = 2 * 1024 * 1024;
const initialSettings = (mode) => ({ mode, zoom: 100, x: 0, y: 0 });

function useImageUrl(source) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!(source instanceof Blob)) {
            setUrl(source || null);
            return;
        }

        const objectUrl = URL.createObjectURL(source);
        setUrl(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [source]);

    return url;
}

function ImageEditor({ draft, onApply, onCancel, id }) {
    const sourceUrl = useImageUrl(draft.source);
    const canvasRef = useRef(null);
    const activeRef = useRef(true);
    const [picture, setPicture] = useState(null);
    const [settings, setSettings] = useState(draft.settings);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        activeRef.current = true;
        return () => { activeRef.current = false; };
    }, []);

    useEffect(() => {
        if (!sourceUrl) return;

        let cancelled = false;
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => { if (!cancelled) setPicture(image); };
        image.onerror = () => {
            if (!cancelled) setError('Gambar tidak dapat dibuka. Silakan pilih file gambar lain.');
        };
        image.src = sourceUrl;
        return () => { cancelled = true; };
    }, [sourceUrl]);

    useEffect(() => {
        if (!picture || !canvasRef.current) return;

        const context = canvasRef.current.getContext('2d');
        const scale = (settings.mode === 'fit' ? Math.min : Math.max)(
            WIDTH / picture.naturalWidth, HEIGHT / picture.naturalHeight,
        ) * settings.zoom / 100;
        const width = picture.naturalWidth * scale;
        const height = picture.naturalHeight * scale;
        const x = (WIDTH - width) / 2 + Math.abs(WIDTH - width) / 2 * settings.x / 100;
        const y = (HEIGHT - height) / 2 + Math.abs(HEIGHT - height) / 2 * settings.y / 100;

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, WIDTH, HEIGHT);
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(picture, x, y, width, height);
    }, [picture, settings]);

    const apply = async () => {
        if (!picture || busy) return;
        setBusy(true);
        setError('');

        try {
            let blob;
            for (const quality of [0.9, 0.8, 0.7, 0.6]) {
                blob = await new Promise((resolve) => canvasRef.current.toBlob(resolve, 'image/jpeg', quality));
                if (!activeRef.current) return;
                if (blob && blob.size <= MAX_OUTPUT_BYTES) break;
            }
            if (!blob || blob.size > MAX_OUTPUT_BYTES) throw new Error('Image export failed');

            const name = draft.source instanceof File ? draft.source.name.replace(/\.[^.]+$/, '') : 'gambar';
            onApply(new File([blob], `${name}.jpg`, { type: 'image/jpeg' }), settings);
        } catch {
            if (activeRef.current) setError('Gambar belum berhasil diproses. Silakan coba lagi atau pilih file lain.');
        } finally {
            if (activeRef.current) setBusy(false);
        }
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Atur gambar</DialogTitle>
                <DialogDescription>
                    Sesuaikan ukuran dan posisi gambar dalam bingkai 4:3 yang digunakan di katalog.
                </DialogDescription>
            </DialogHeader>
            <div className="relative mx-auto w-full max-w-md overflow-hidden rounded-lg border bg-white">
                <canvas ref={canvasRef} width={WIDTH} height={HEIGHT} className="block aspect-[4/3] w-full"
                    role="img" aria-label="Preview hasil gambar di katalog" />
                {!picture && <div className="absolute inset-0 flex items-center justify-center text-sm text-slate-600">
                    {error ? 'Preview tidak tersedia' : 'Memuat gambar…'}
                </div>}
            </div>
            <fieldset disabled={!picture || busy} className="space-y-4 disabled:opacity-50">
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant={settings.mode === 'fit' ? 'orange' : 'outline'}
                        aria-pressed={settings.mode === 'fit'} onClick={() => setSettings(initialSettings('fit'))}>
                        Gambar utuh
                    </Button>
                    <Button type="button" variant={settings.mode === 'fill' ? 'orange' : 'outline'}
                        aria-pressed={settings.mode === 'fill'} onClick={() => setSettings(initialSettings('fill'))}>
                        Penuhi bingkai
                    </Button>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        { key: 'zoom', label: `Zoom (${settings.zoom}%)`, min: settings.mode === 'fill' ? 100 : 50, max: 300 },
                        { key: 'x', label: 'Posisi horizontal', min: -100, max: 100 },
                        { key: 'y', label: 'Posisi vertikal', min: -100, max: 100 },
                    ].map(({ key, label, min, max }) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`${id}-${key}`}>{label}</Label>
                            <input id={`${id}-${key}`} type="range" min={min} max={max} step="1"
                                value={settings[key]} className="h-6 w-full cursor-pointer accent-orange-500"
                                onChange={(event) => setSettings((current) => ({ ...current, [key]: Number(event.target.value) }))} />
                        </div>
                    ))}
                </div>
                <p className="text-xs text-muted-foreground">Area kosong dan transparan akan berlatar putih.</p>
            </fieldset>
            {error && <div role="alert"><InputError message={error} /></div>}
            <DialogFooter className="gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>Batal</Button>
                <Button type="button" variant="orange" disabled={!picture || busy} onClick={apply}>
                    {busy ? 'Memproses…' : 'Terapkan gambar'}
                </Button>
            </DialogFooter>
        </>
    );
}

const ImageUpload = forwardRef(function ImageUpload({ id, value, onChange, existingUrl = null, defaultMode = 'fit', previewFit = 'cover', disabled = false }, ref) {
    const previewUrl = useImageUrl(value || existingUrl);
    const [selection, setSelection] = useState(null);
    const [draft, setDraft] = useState(null);
    const [error, setError] = useState('');

    useImperativeHandle(ref, () => ({
        reset() {
            setSelection(null);
            setDraft(null);
            setError('');
        },
    }), []);

    const chooseFile = (event) => {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) return;
        setError('');

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setError('Pilih gambar JPG, PNG, atau WebP.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            setError('Ukuran gambar awal maksimal 10 MB.');
            return;
        }
        setDraft({ source: file, settings: initialSettings(defaultMode) });
    };

    return (
        <div className="space-y-3">
            <Input id={id} type="file" accept="image/jpeg,image/png,image/webp" disabled={disabled}
                onChange={chooseFile} aria-describedby={`${id}-help`} />
            <p id={`${id}-help`} className="text-xs text-muted-foreground">
                JPG, PNG, atau WebP, maksimal 10 MB. Pilih gambar untuk mengatur ukuran dan posisinya.
            </p>
            {error && <div role="alert"><InputError message={error} /></div>}
            {previewUrl && (
                <div className="max-w-xs space-y-2">
                    <div className="aspect-[4/3] overflow-hidden rounded-lg border bg-white">
                        <img src={previewUrl} alt="Preview gambar yang akan ditampilkan"
                            className={`size-full ${previewFit === 'contain' ? 'object-contain' : 'object-cover'}`} />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {value ? 'Preview hasil pengaturan. Klik Save untuk menyimpan.' : 'Gambar saat ini.'}
                    </p>
                    <Button type="button" variant="outline" disabled={disabled} onClick={() => setDraft({
                        source: selection?.source ?? value ?? existingUrl,
                        settings: selection?.settings ?? initialSettings(defaultMode),
                    })}>Atur gambar</Button>
                </div>
            )}
            <Dialog open={Boolean(draft)} onOpenChange={(open) => { if (!open) setDraft(null); }}>
                <DialogContent className="max-h-[90dvh] max-w-2xl overflow-y-auto" onKeyDown={(event) => {
                    if (event.key === 'Enter' && event.target.tagName !== 'BUTTON') event.preventDefault();
                }}>
                    {draft && <ImageEditor id={id} draft={draft} onCancel={() => setDraft(null)}
                        onApply={(file, settings) => {
                            onChange(file);
                            setSelection({ source: draft.source, settings });
                            setDraft(null);
                        }} />}
                </DialogContent>
            </Dialog>
        </div>
    );
});

export default ImageUpload;
