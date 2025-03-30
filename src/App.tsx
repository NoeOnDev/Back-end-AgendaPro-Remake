import * as React from 'react';
import DashboardIcon from '@mui/icons-material/Dashboard';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import { Outlet, useNavigate } from 'react-router';
import { ReactRouterAppProvider } from '@toolpad/core/react-router';
import type { Navigation, Authentication } from '@toolpad/core/AppProvider';
import SessionContext, { type Session } from './SessionContext';
import { signOut, getCurrentUser } from './services/auth';

const NAVIGATION: Navigation = [
  {
    kind: 'header',
    title: 'Main items',
  },
  {
    title: 'Dashboard',
    icon: <DashboardIcon />,
  },
  {
    segment: 'orders',
    title: 'Orders',
    icon: <ShoppingCartIcon />,
  },
];

const BRANDING = {
  title: "ismekalf",
};

export default function App() {
  const [session, setSession] = React.useState<Session | null>(null);
  const [loading, setLoading] = React.useState(true);
  const navigate = useNavigate();

  const handleSignOut = async () => {
    await signOut();
    setSession(null);
    navigate('/sign-in');
  };

  const AUTHENTICATION: Authentication = {
    signIn: () => { },
    signOut: handleSignOut,
  };

  const sessionContextValue = React.useMemo(
    () => ({
      session,
      setSession,
      loading,
    }),
    [session, loading],
  );

  React.useEffect(() => {
    const checkAuthStatus = async () => {
      try {
        const result = await getCurrentUser();
        if (result.success && result.user) {
          setSession({
            user: {
              id: result.user.id?.toString(),
              name: result.user.name || '',
              email: result.user.email || '',
              image: result.user.image || '',
              role: result.user.role || '',
              emailVerified: !!result.user.email_verified_at,
            },
            token: result.token,
          });
        } else {
          setSession(null);
        }
      } catch (error) {
        console.error('Error al verificar sesión:', error);
        setSession(null);
      } finally {
        setLoading(false);
      }
    };

    checkAuthStatus();
  }, []);

  return (
    <ReactRouterAppProvider
      navigation={NAVIGATION}
      branding={BRANDING}
      session={session}
      authentication={AUTHENTICATION}
      localeText={{
        accountSignInLabel: 'Iniciar sesión',
        accountSignOutLabel: 'Cerrar sesión',
        accountPreviewTitle: 'Mi cuenta',
      }}
    >
      <SessionContext.Provider value={sessionContextValue}>
        <Outlet />
      </SessionContext.Provider>
    </ReactRouterAppProvider>
  );
}