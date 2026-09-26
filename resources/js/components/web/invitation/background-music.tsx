import { Pause, Play } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Play/pause toggle for the template's <audio data-emp-bg-music>.
 * Browsers block autoplay with sound, so music starts on the guest's tap.
 */
export function BackgroundMusic({ root }: { root: ShadowRoot | null }) {
    const audio = root?.querySelector<HTMLAudioElement>(
        'audio[data-emp-bg-music]',
    );
    const [playing, setPlaying] = useState(false);

    useEffect(() => {
        if (!audio) {
            return;
        }

        const sync = () => setPlaying(!audio.paused);
        audio.addEventListener('play', sync);
        audio.addEventListener('pause', sync);

        return () => {
            audio.removeEventListener('play', sync);
            audio.removeEventListener('pause', sync);
            audio.pause();
        };
    }, [audio]);

    if (!audio) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => (audio.paused ? void audio.play() : audio.pause())}
            aria-label={playing ? 'Pause music' : 'Play music'}
            className="fixed right-4 bottom-4 z-50 flex items-center gap-2 rounded-full bg-black/70 px-4 py-2.5 text-sm font-medium text-white shadow-lg backdrop-blur transition hover:bg-black/80 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
        >
            {playing ? (
                <Pause className="size-4" />
            ) : (
                <Play className="size-4" />
            )}
            {playing ? 'Pause music' : 'Play music'}
        </button>
    );
}
