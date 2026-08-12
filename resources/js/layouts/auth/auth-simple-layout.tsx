import { Link } from '@inertiajs/react';
import { Shield, Swords } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="rune-auth-shell">
            <div className="rune-auth-haze" aria-hidden="true" />

            <div className="rune-auth-wrap">
                <Link href={home()} className="rune-auth-brand">
                    <div className="rune-auth-crest" aria-hidden="true">
                        <Shield className="rune-auth-shield" />
                        <Swords className="rune-auth-swords" />
                    </div>
                    <div>
                        <div className="rune-auth-name">RuneVentures</div>
                        <div className="rune-auth-tagline">A medieval adventure awaits</div>
                    </div>
                </Link>

                <section className="rune-auth-panel">
                    <span className="rune-auth-corner rune-auth-corner-tl" aria-hidden="true" />
                    <span className="rune-auth-corner rune-auth-corner-tr" aria-hidden="true" />
                    <span className="rune-auth-corner rune-auth-corner-bl" aria-hidden="true" />
                    <span className="rune-auth-corner rune-auth-corner-br" aria-hidden="true" />

                    <header className="rune-auth-heading">
                        <p className="rune-auth-kicker">Adventurer access</p>
                        <h1>{title}</h1>
                        {description && <p>{description}</p>}
                    </header>

                    <div className="rune-auth-content">{children}</div>
                </section>

                <p className="rune-auth-footer">RuneVentures · Enter the realm</p>
            </div>
        </div>
    );
}
