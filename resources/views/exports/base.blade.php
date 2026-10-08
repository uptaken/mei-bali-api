<!DOCTYPE html>
<html lang="id" class="bg-white text-[13px]">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	@yield('style')
	@yield('top_script')
</head>

<body class="@yield('body_class', 'm-0 bg-white text-[#242424] [font-family:Arial,Helvetica,sans-serif] text-[12px] leading-[1.45]')">
	@yield('content')

	@yield('script')
</body>

</html>
