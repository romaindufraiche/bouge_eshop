import Image from 'next/image';
import { LoginForm } from '@/app/admin/connexion/LoginForm';

type PageProps = { searchParams: Promise<{ suite?: string }> };

export default async function LoginPage({ searchParams }: PageProps) {
  const { suite } = await searchParams;

  return (
    <div className="flex flex-1 items-center justify-center px-5 py-16">
      <div className="w-full max-w-sm">
        <Image
          src="/brand/wordmark-anthracite.png"
          alt="BOUGE."
          width={720}
          height={346}
          priority
          className="h-8 w-auto"
        />
        <h1 className="mt-6 text-2xl">Administration</h1>
        <p className="mt-2 text-sm text-ink-soft">
          Connectez-vous pour gérer le catalogue et les commandes.
        </p>

        <div className="mt-8">
          <LoginForm next={suite} />
        </div>
      </div>
    </div>
  );
}
