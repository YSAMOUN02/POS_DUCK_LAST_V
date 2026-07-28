<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>
       :root {
        --pos-blue: #1687f2;
        --pos-blue-dark: #0f67d8;
        --pos-soft: #eef6ff;
        --pos-text: #102a56;
    }

    body {
        margin: 0;
        height: 100vh;
        overflow: hidden;
        position: relative;
        font-family: Inter, system-ui, sans-serif;
        background: #f4f9ff;
    }

    body::before {
        content: "";
        position: fixed;
        inset: 0;
        background: url('{{ asset('assets/background/login_background.png') }}') no-repeat center center;
        background-size: cover;
        z-index: -2;
    }

    body::after {
        content: "";
        position: fixed;
        inset: 0;
        background: linear-gradient(90deg,
            rgba(255,255,255,.15),
            rgba(239,246,255,.45),
            rgba(255,255,255,.15)
        );
        z-index: -1;
    }

    .login-layout {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }

    .login-form {
        /* fluid up to 360px — a hard 360px width plus the 30px side padding and
           the layout's 20px gutter needed ~420px, so the card ran off the right
           edge of any phone narrower than that */
        width: 100%;
        max-width: 360px;
        padding: 34px 30px;
        border-radius: 26px;
        background: rgba(255, 255, 255, .78);
        border: 1px solid rgba(255, 255, 255, .9);
        box-shadow:
            0 24px 70px rgba(15, 103, 216, .22),
            inset 0 1px 0 rgba(255,255,255,.8);
        backdrop-filter: blur(18px);
    }

    .login-form h1 {
        margin-bottom: 24px;
        text-align: center;
        font-size: 34px;
        font-weight: 800;
        color: var(--pos-text);
    }

    .login-form h1 span {
        background: linear-gradient(90deg, #1687f2, #6d5dfc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Narrow phones: trade padding for input width, and keep the inputs at 16px
       so iOS Safari doesn't zoom the page when one is focused. */
    @media (max-width: 420px) {
        .login-layout { padding: 12px; }
        .login-form { padding: 26px 18px; }
        .login-form h1 { font-size: 28px; margin-bottom: 18px; }
        .login-form input[type="text"],
        .login-form input[type="password"] { font-size: 16px; }
    }

    .login-form label {
        color: var(--pos-text);
        font-size: 13px;
        font-weight: 700;
    }

    .login-form input[type="text"],
    .login-form input[type="password"] {
        width: 100%;
        height: 46px;
        margin-top: 8px;
        margin-bottom: 18px;
        padding: 0 14px;
        border-radius: 14px;
        border: 1px solid #cfe4ff;
        background: rgba(255,255,255,.9);
        color: #0f172a;
        font-size: 14px;
        outline: none;
        box-shadow: 0 6px 18px rgba(15,103,216,.08);
        transition: .2s ease;
    }

    .login-form input::placeholder {
        color: #9aaec8;
    }

    .login-form input:focus {
        border-color: var(--pos-blue);
        box-shadow: 0 0 0 4px rgba(22,135,242,.16);
        background: #fff;
    }

    .login-form input[type="checkbox"] {
        width: 15px;
        height: 15px;
        accent-color: var(--pos-blue);
    }

    .login-form small {
        color: #475569;
        font-weight: 600;
    }

    .login-form button {
        width: 100%;
        height: 48px;
        border: none;
        border-radius: 15px;
        background: linear-gradient(135deg, #1687f2, #0066ff);
        color: white;
        font-size: 15px;
        font-weight: 800;
        box-shadow: 0 14px 28px rgba(22,135,242,.35);
        transition: .2s ease;
    }

    .login-form button:hover {
        transform: translateY(-1px);
        background: linear-gradient(135deg, #0f67d8, #0057e7);
        box-shadow: 0 18px 36px rgba(22,135,242,.45);
    }

    #toastMessage {
        border: 1px solid #dbeafe;
    }
    </style>
    <title>POS System - Login</title>
</head>

<body>


    <div class="login-layout">
        <form id="LoginForm" action="/login/submit" method="post" class="login-form max-w-sm mx-auto">
            @csrf
            <h1 class="mb-4 text-3xl font-bold text-heading md:text-3xl lg:text-4xl"><span
                    class="text-transparent bg-clip-text bg-gradient-to-r to-amber-600 from-amber-400">Login</span>
            </h1>

            <div class="mb-5">
                <label for="name_email" class="block mb-2.5 text-sm font-medium text-heading">User</label>
                <input type="text" id="name_email" name="name_email"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow placeholder:text-body"
                    placeholder="Candy" required />

            </div>
            <div class="mb-5">
                <label for="password-alternative" class="block mb-2.5 text-sm font-medium text-heading">
                    password</label>
                <input type="password" id="password-alternative" name="password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow placeholder:text-body"
                    placeholder="••••••••" required />

            </div>
            <div class="flex items-center mb-5">

                <input type="checkbox" id="remember-me" name="remember_me" required />
                &ensp;
                <small>
                    Remember Me</small>

            </div>
            <button type="button" id="loginBtn"
                class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium rounded-base text-sm px-4 py-2.5 focus:outline-none">
                Submit
            </button>
        </form>
    </div>

    {{-- Toast  --}}
    <div id="toastMessage"
        class="fixed top-5 right-5 z-50 hidden flex items-center justify-between max-w-sm w-full bg-white rounded-2xl shadow-2xl p-4 animate-scaleUp">

        <div class="flex items-center space-x-3">
            <span id="toastIcon" class="text-green-500 text-xl">✔️</span>
            <p id="toastText" class="text-gray-800 font-medium"></p>
        </div>

        <button onclick="hideToast()" class="text-gray-500 hover:text-gray-700 text-xl font-bold">&times;</button>
    </div>
</body>
<script>
    // GLOBAL TOAST
    let toastTimeout;

    function showToast({
        message,
        type = "success",
        duration = 5000
    }) {
        const toast = document.getElementById("toastMessage");
        const text = document.getElementById("toastText");
        const icon = document.getElementById("toastIcon");

        // Set message
        text.innerText = message;

        // Set icon and color
        switch (type) {
            case "success":
                toast.classList.remove("bg-red-500", "bg-yellow-500");
                icon.innerText = "✔️";
                icon.classList.add("text-green-500");
                break;
            case "error":
                toast.classList.remove("bg-green-500", "bg-yellow-500");
                icon.innerText = "❌";
                icon.classList.add("text-red-500");
                break;
            case "warning":
                toast.classList.remove("bg-green-500", "bg-red-500");
                icon.innerText = "⚠️";
                icon.classList.add("text-yellow-500");
                break;
        }

        toast.classList.remove("hidden");

        // Auto hide after duration
        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            hideToast();
        }, duration);
    }
    // GLOBAL HIDE TOAST
    function hideToast() {
        const toast = document.getElementById("toastMessage");
        toast.classList.add("hidden");

        // Optional: reset icon and text
        document.getElementById("toastText").innerText = "";
        document.getElementById("toastIcon").innerText = "✔️";
    }


    document.getElementById("loginBtn").addEventListener("click", async function() {
        const form = document.getElementById("LoginForm"); // your form element
        const formData = new FormData(form);
        const remember_me = formData.get("remember_me") === "on"; // checkbox value
        const name_email = formData.get("name_email").trim();
        const password = formData.get("password").trim();
        if (!name_email) {
            showToast({
                message: "Name or Email Required.",
                type: "error"
            });
            return;
        }
        if (!password) {
            showToast({
                message: "Password Required.",
                type: "error"
            });
            return;
        }
        try {
            const res = await fetch("/login-submit", {
                method: "POST",
                body: JSON.stringify({
                    name_email,
                    password,
                    remember_me
                }),
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                        .value,
                    Accept: "application/json",
                    "Content-Type": "application/json", // 🔥 must have
                }
            });

            const data = await res.json();

            if (data.success) {
                showToast({
                    message: data.message,
                    type: "success"
                });
                setTimeout(() => {
             window.location.href = data.redirect;
                }, 1000);
            } else {
                showToast({
                    message: data.message,
                    type: "error"
                });
            }

        } catch (err) {
            console.error(err);
            showToast({
                message: "Server error, try again ❌",
                type: "error"
            });
        }
    });
    document.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault(); // prevent any default form submit
        const loginBtn = document.getElementById("loginBtn");
        if (loginBtn) {
            loginBtn.click(); // trigger your login JS
        }
    }
});
</script>

</html>
