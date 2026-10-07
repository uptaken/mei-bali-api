<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
	<style>
	html{
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

	<script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.0/moment.min.js"></script>

	@yield('script')
</body>

</html>
