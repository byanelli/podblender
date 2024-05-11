@use(App\Http\Routes\Web)

<h1>Login</h1>
<form action="{{Web::showLogin()}}" method="post">
    @csrf
    Email:<input name="email"/><br>
    Password:<input name="password"/><br>
    <button type="submit">Login</button>
</form>
