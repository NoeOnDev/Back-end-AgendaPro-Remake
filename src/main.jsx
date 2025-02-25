import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { AppProvider } from "@toolpad/core";
import { CssBaseline } from "@mui/material";
import "@fontsource/roboto/300.css";
import "@fontsource/roboto/400.css";
import "@fontsource/roboto/500.css";
import "@fontsource/roboto/700.css";

import { App } from "./containers/App";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <AppProvider>
      <CssBaseline />
      <App />
    </AppProvider>
  </StrictMode>
);
