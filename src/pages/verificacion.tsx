import * as React from 'react';
import { useNavigate, useLocation } from 'react-router';
import { Box, Typography, Button, Paper } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutline';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { green, red, blue } from '@mui/material/colors';

export default function VerificacionPage() {
    const location = useLocation();
    const navigate = useNavigate();
    const params = new URLSearchParams(location.search);
    const estado = params.get('estado');

    let titulo = '';
    let mensaje = '';
    let icono: React.ReactNode = null;
    let color = '';

    switch (estado) {
        case 'exito':
            titulo = '¡Email verificado correctamente!';
            mensaje = 'Tu cuenta ha sido verificada correctamente. Ahora puedes iniciar sesión en la plataforma.';
            icono = <CheckCircleIcon sx={{ fontSize: 60, color: green[500] }} />;
            color = green[500];
            break;
        case 'error':
            titulo = 'Error de verificación';
            mensaje = 'No se pudo verificar tu cuenta. El enlace podría ser inválido o haber expirado.';
            icono = <ErrorOutlineIcon sx={{ fontSize: 60, color: red[500] }} />;
            color = red[500];
            break;
        case 'ya-verificado':
            titulo = 'Cuenta ya verificada';
            mensaje = 'Esta cuenta ya había sido verificada anteriormente. Puedes iniciar sesión normalmente.';
            icono = <InfoOutlinedIcon sx={{ fontSize: 60, color: blue[500] }} />;
            color = blue[500];
            break;
        default:
            titulo = 'Estado desconocido';
            mensaje = 'No se pudo determinar el estado de la verificación.';
            icono = <ErrorOutlineIcon sx={{ fontSize: 60, color: red[500] }} />;
            color = red[500];
    }

    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                minHeight: '100vh',
                padding: 2,
                bgcolor: '#f5f5f5'
            }}
        >
            <Paper
                elevation={3}
                sx={{
                    p: 4,
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    maxWidth: 500,
                    width: '100%'
                }}
            >
                <Box sx={{ mb: 2 }}>
                    {icono}
                </Box>
                <Typography variant="h5" component="h1" gutterBottom align="center" sx={{ color }}>
                    {titulo}
                </Typography>
                <Typography variant="body1" align="center" paragraph>
                    {mensaje}
                </Typography>
                <Box sx={{ mt: 3 }}>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={() => navigate('/sign-in')}
                    >
                        Ir a iniciar sesión
                    </Button>
                </Box>
            </Paper>
        </Box>
    );
}