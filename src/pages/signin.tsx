'use client';
import * as React from 'react';
import LinearProgress from '@mui/material/LinearProgress';
import { SignInPage } from '@toolpad/core/SignInPage';
import { Navigate, useNavigate } from 'react-router';
import { useSession, type Session } from '../SessionContext';
import { signInWithCredentials } from '../services/auth';

export default function SignIn() {
  const { session, setSession, loading } = useSession();
  const navigate = useNavigate();
  const [loginError, setLoginError] = React.useState<string | null>(null);

  if (loading) {
    return <LinearProgress />;
  }

  if (session) {
    return <Navigate to="/" />;
  }

  return (
    <SignInPage
      providers={[{ id: 'credentials', name: 'Credenciales' }]}
      error={loginError}
      signIn={async (provider, formData, callbackUrl) => {
        try {
          if (provider.id === 'credentials') {
            const email = formData?.get('email') as string;
            const password = formData?.get('password') as string;

            if (!email || !password) {
              setLoginError('El email y la contraseña son obligatorios');
              return { error: 'El email y la contraseña son obligatorios' };
            }

            const result = await signInWithCredentials(email, password);

            if (result.success && result.user) {
              const userSession: Session = {
                user: {
                  id: result.user.id?.toString(),
                  name: result.user.name || '',
                  email: result.user.email || '',
                  image: result.user.image || '',
                  role: result.user.role || '',
                  emailVerified: !!result.user.email_verified_at,
                },
                token: result.token,
              };
              setSession(userSession);
              navigate(callbackUrl || '/', { replace: true });
              return {};
            }

            const errorMsg = result.error || 'Error al iniciar sesión';
            setLoginError(errorMsg);
            return { error: errorMsg };
          }

          setLoginError('Proveedor de autenticación no soportado');
          return { error: 'Proveedor de autenticación no soportado' };
        } catch (error) {
          const errorMsg = error instanceof Error ? error.message : 'Se produjo un error';
          setLoginError(errorMsg);
          return { error: errorMsg };
        }
      }}
    />
  );
}