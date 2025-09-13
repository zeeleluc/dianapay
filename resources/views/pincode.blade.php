<!DOCTYPE html>
<html>
<head>
    <title>Pincode</title>
</head>
<body>
<h1>Enter Pincode</h1>

@if(isset($errors) && $errors->any())
    <div style="color:red;">{{ $errors->first('pincode') }}</div>
@endif

<form method="POST" action="{{ route('pincode.check') }}">
    @csrf
    <input type="password" name="pincode" placeholder="Enter pincode">
    <button type="submit">Submit</button>
</form>
</body>
</html>
