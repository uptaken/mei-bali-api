<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		html {
			font-size: 13px;
			background-color: white;
		}
	</style>
	@yield('style')
	@yield('top_script')
</head>

<body>


	<div class="">
	@yield('content')
	</div>

	@yield('script')
</body>

</html>
