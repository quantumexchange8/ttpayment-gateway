import { Link, Head } from '@inertiajs/react';

export default function Welcome({ auth, laravelVersion, phpVersion }) {
    const handleImageError = () => {
        document.getElementById('screenshot-container')?.classList.add('!hidden');
        document.getElementById('docs-card')?.classList.add('!row-span-1');
        document.getElementById('docs-card-content')?.classList.add('!flex-row');
        document.getElementById('background')?.classList.add('!hidden');
    };

    return (
        <>
            <div className="flex justify-center items-center min-h-screen">
                <div className=" w-96 h-20 bg-white text-3xl text-black font-bold flex items-center justify-center">
                    Invalid session / Session Timeout
                </div>
            </div>
        </>
    );
}
