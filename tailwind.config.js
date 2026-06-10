/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./assets/**/*.js", "./templates/**/*.html.twig"],
    theme: {
        extend: {
            fontFamily: {
                // Display/headings — Stardust per the kit, with an approved fallback stack.
                display: ['"Stardust Condensed"', '"Stardust Local"', "system-ui", "-apple-system", '"Segoe UI"', "sans-serif"],
                // Body/UI — system sans by design (NOT Times New Roman; see app.css).
                body: ["system-ui", "-apple-system", '"Segoe UI"', "Roboto", "Helvetica", "Arial", "sans-serif"],
                // Times reserved for intentional editorial/serif text only.
                serifbody: ['"Times New Roman"', "Times", "serif"],
            },
        },
    },
    plugins: [require("daisyui")],
    daisyui: {
        // Single custom theme built entirely from the Innovate Alabama palette.
        themes: [
            {
                innovate: {
                    primary: "#0043e8", // brand blue — primary accent / result bars
                    "primary-content": "#ffffff",
                    secondary: "#00B2E3", // logo cyan — secondary accent / leading highlight
                    "secondary-content": "#000000",
                    accent: "#28f2e6", // lighter cyan — glow/hover tint only
                    "accent-content": "#000000",
                    neutral: "#000000", // black — kit buttons
                    "neutral-content": "#ffffff",
                    "base-100": "#ffffff", // white background per kit
                    "base-200": "#f4f4f4",
                    "base-300": "#e6e6e6",
                    "base-content": "#000000", // black text per kit
                    info: "#0043e8",
                    "info-content": "#ffffff",
                    success: "#00B2E3",
                    "success-content": "#000000",
                    warning: "#9a9a9a", // neutral gray
                    "warning-content": "#000000",
                    error: "#b00020", // functional red for destructive admin actions only
                    "error-content": "#ffffff",
                    "--rounded-box": "0", // sharp corners — minimal kit aesthetic
                    "--rounded-btn": "0", // square buttons (kit: 0px radius)
                    "--rounded-badge": "0",
                    "--animation-btn": "0.2s",
                    "--border-btn": "1px",
                    "--tab-radius": "0",
                },
            },
            {
                // Dark variant — same brand accents on a near-black base.
                "innovate-dark": {
                    primary: "#0043e8",
                    "primary-content": "#ffffff",
                    secondary: "#00B2E3",
                    "secondary-content": "#000000",
                    accent: "#28f2e6",
                    "accent-content": "#000000",
                    neutral: "#ffffff", // inverted for dark: light buttons
                    "neutral-content": "#000000",
                    "base-100": "#0a0a0a", // near-black background
                    "base-200": "#161616",
                    "base-300": "#242424",
                    "base-content": "#ffffff", // white text
                    info: "#0043e8",
                    "info-content": "#ffffff",
                    success: "#00B2E3",
                    "success-content": "#000000",
                    warning: "#9a9a9a",
                    "warning-content": "#000000",
                    error: "#ff5a5a",
                    "error-content": "#000000",
                    "--rounded-box": "0",
                    "--rounded-btn": "0",
                    "--rounded-badge": "0",
                    "--animation-btn": "0.2s",
                    "--border-btn": "1px",
                    "--tab-radius": "0",
                },
            },
        ],
        darkTheme: "innovate-dark",
        logs: false,
    },
};
