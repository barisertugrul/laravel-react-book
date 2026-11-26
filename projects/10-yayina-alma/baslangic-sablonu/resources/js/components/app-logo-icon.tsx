import type { SVGAttributes } from 'react';

// Yer tutucu ürün logosu (üst üste üç katman). Kendi ürününüzün logosuyla
// değiştirin; public/favicon.svg aynı çizimi kullanır.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 3 37 11.5 20 20 3 11.5Z" />
            <path d="M3 19.5 7.5 17.25 20 23.5 32.5 17.25 37 19.5 20 28Z" />
            <path d="M3 27.5 7.5 25.25 20 31.5 32.5 25.25 37 27.5 20 36Z" />
        </svg>
    );
}
