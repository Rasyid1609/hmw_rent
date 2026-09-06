import { Moon, Sun } from 'lucide-react';
import { useTheme } from './ThemeProvider';
import { Button } from './ui/button';

export default function ThemeSwitcher({ className = 'ml-auto' }) {
    const { setTheme } = useTheme();
    const toggleTheme = () => {
        setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            className={className}
            onClick={toggleTheme}
            aria-label="Ganti tema terang atau gelap"
            title="Ganti tema terang atau gelap"
        >
            <Sun className="hidden h-4 w-4 dark:block" aria-hidden="true" />
            <Moon className="h-4 w-4 dark:hidden" aria-hidden="true" />
        </Button>
    );
}
