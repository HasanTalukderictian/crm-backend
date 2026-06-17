<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8">
    <title>Account Created</title>
</head>
<body>

```
<h2>Welcome {{ $user->name }}</h2>

<p>Your account has been created successfully.</p>

<p><strong>Email:</strong> {{ $user->email }}</p>
<p><strong>Password:</strong> {{ $password }}</p>

<p>
    <a href="http://crm.akashbariholidays.org/"
       style="background-color:#007bff;
              color:#ffffff;
              padding:12px 24px;
              text-decoration:none;
              border-radius:5px;
              display:inline-block;">
        Login to CRM
    </a>
</p>

<p>
    Or copy and paste this URL into your browser:
    <br>
    <a href="http://crm.akashbariholidays.org/">
        http://crm.akashbariholidays.org/
    </a>
</p>

<br>

<p>Thanks for joining.</p>

<p>
    Regards,<br>
    Akash Bari Holidays Team
</p>
```

</body>
</html>

