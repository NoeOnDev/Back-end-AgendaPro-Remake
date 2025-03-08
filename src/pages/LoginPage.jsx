import { SignInPage } from "@toolpad/core/SignInPage";
import {
  TextField,
  FormControl,
  OutlinedInput,
  InputLabel,
  InputAdornment,
  IconButton,
  Button,
  Link,
} from "@mui/material";
import {
  Person as PersonIcon,
  Lock as LockIcon,
  Visibility as VisibilityIcon,
  VisibilityOff as VisibilityOffIcon,
} from "@mui/icons-material";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { AuthService } from "../services/api/auth.service";

const providers = [{ id: "credentials", name: "Email y Contraseña" }];

function CustomEmailField() {
  return (
    <TextField
      margin="normal"
      required
      fullWidth
      id="username"
      label="Correo electrónico o usuario"
      name="username"
      autoComplete="email"
      autoFocus
      InputProps={{
        startAdornment: (
          <InputAdornment position="start">
            <PersonIcon />
          </InputAdornment>
        ),
      }}
    />
  );
}

function CustomPasswordField() {
  const [showPassword, setShowPassword] = useState(false);

  return (
    <FormControl fullWidth margin="normal">
      <InputLabel>Contraseña</InputLabel>
      <OutlinedInput
        required
        name="password"
        label="Contraseña"
        type={showPassword ? "text" : "password"}
        autoComplete="current-password"
        startAdornment={
          <InputAdornment position="start">
            <LockIcon />
          </InputAdornment>
        }
        endAdornment={
          <InputAdornment position="end">
            <IconButton
              onClick={() => setShowPassword(!showPassword)}
              edge="end"
            >
              {showPassword ? <VisibilityOffIcon /> : <VisibilityIcon />}
            </IconButton>
          </InputAdornment>
        }
      />
    </FormControl>
  );
}

function CustomButton() {
  return (
    <Button type="submit" fullWidth variant="contained" sx={{ mt: 3, mb: 2 }}>
      Iniciar Sesión
    </Button>
  );
}

function ForgotPasswordLink() {
  const navigate = useNavigate();

  return (
    <Link
      component="button"
      variant="body2"
      onClick={() => navigate("/forgot-password")}
      sx={{ mt: 1 }}
    >
      ¿Olvidaste tu contraseña?
    </Link>
  );
}

function Title() {
  return <h2>Iniciar Sesión</h2>;
}

function EmptySubtitle() {
  return null;
}

function EmptyRememberMe() {
  return null;
}

export function LoginPage() {
  const navigate = useNavigate();
  const [error, setError] = useState(null);

  const handleSignIn = async (provider, formData) => {
    try {
      const username = formData.get("username");
      const password = formData.get("password");

      const response = await AuthService.login(username, password);

      console.log("Usuario autenticado:", response.user);
      navigate("/dashboard");
    } catch (err) {
      setError(err.message);
      console.error("Error de autenticación:", err);
    }
  };

  return (
    <SignInPage
      signIn={handleSignIn}
      error={error}
      slots={{
        title: Title,
        subtitle: EmptySubtitle,
        emailField: CustomEmailField,
        passwordField: CustomPasswordField,
        submitButton: CustomButton,
        forgotPasswordLink: ForgotPasswordLink,
        rememberMe: EmptyRememberMe,
      }}
      providers={providers}
    />
  );
}
