import { useState } from "react";
import {
  Box,
  Paper,
  TextField,
  Button,
  Typography,
  Container,
  InputAdornment,
} from "@mui/material";
import { Email as EmailIcon } from "@mui/icons-material";

export function ForgotPasswordPage() {
  const [email, setEmail] = useState("");

  const handleSubmit = (event) => {
    event.preventDefault();
    console.log("Email enviado:", email);
  };

  return (
    <Container component="main" maxWidth="xs">
      <Box
        sx={{
          marginTop: 8,
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
        }}
      >
        <Paper
          elevation={3}
          sx={{
            padding: 4,
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            width: "100%",
          }}
        >
          <Typography component="h1" variant="h5" sx={{ mb: 3 }}>
            Recuperar Contraseña
          </Typography>
          <Typography variant="body2" sx={{ mb: 3, textAlign: "center" }}>
            Ingresa tu correo electrónico y te enviaremos las instrucciones para
            restablecer tu contraseña.
          </Typography>

          <Box component="form" onSubmit={handleSubmit} sx={{ width: "100%" }}>
            <TextField
              margin="normal"
              required
              fullWidth
              id="email"
              label="Correo electrónico"
              name="email"
              autoComplete="email"
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <EmailIcon />
                  </InputAdornment>
                ),
              }}
            />

            <Button
              type="submit"
              fullWidth
              variant="contained"
              sx={{ mt: 3, mb: 2 }}
            >
              Enviar instrucciones
            </Button>
          </Box>
        </Paper>
      </Box>
    </Container>
  );
}
