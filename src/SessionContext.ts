import * as React from "react";

export interface Session {
  user: {
    id?: string;
    name?: string;
    email?: string;
    image?: string;
    role?: string;
    emailVerified?: boolean;
  };
  token?: string;
}

interface SessionContextType {
  session: Session | null;
  setSession: (session: Session | null) => void;
  loading: boolean;
}

const SessionContext = React.createContext<SessionContextType>({
  session: null,
  setSession: () => {},
  loading: true,
});

export default SessionContext;

export const useSession = () => React.useContext(SessionContext);
