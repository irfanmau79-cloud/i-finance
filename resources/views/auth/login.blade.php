<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Login - i-Finance</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        .login-box {
            max-width: 320px;
            margin: 60px auto;
            border: 1px solid #999;
            padding: 20px;
        }

        .login-box h1 {
            font-size: 20px;
            margin-top: 0;
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 4px;
        }

        .field input {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
        }

        .error {
            border: 1px solid #a00;
            background-color: #fee;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Login i-Finance</h1>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
