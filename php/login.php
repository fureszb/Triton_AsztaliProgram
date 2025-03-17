<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === 'triton' && $password === '123') {
        $_SESSION['loggedin'] = true;
        header('Location: list.php');
        exit;
    } else {
        echo "Hibás felhasználónév vagy jelszó!";
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/login.css">



</head>

<body>
    <!--<div class="container">
        <h1>Bejelentkezés</h1>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Felhasználónév:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Jelszó:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Bejelentkezés</button>
        </form>
    </div>-->

    <div class="loginBox"> <img class="user" src="..\images\tritonLogo.webp" height="100px" width="100px">
        <h3>Bejelentkezés</h3>
        <form action="login.php" method="POST">
            <div class="inputBox"> <input id="uname" type="text" name="username" placeholder="Felhasználónév" required> <input id="pass" type="password" name="password" placeholder="Jelszó" required> </div> <input type="submit" name="" value="Bejelentkezés">
        </form>
        <a href="#">Elfelejtett jelszó<br> </a>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>