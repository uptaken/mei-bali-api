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
	<?php echo $__env->yieldContent('style'); ?>
	<?php echo $__env->yieldContent('top_script'); ?>
</head>

<body>


	<div class="">
	<?php echo $__env->yieldContent('content'); ?>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.0/moment.min.js"></script>

	<?php echo $__env->yieldContent('script'); ?>
</body>

</html>
<?php /**PATH /mnt/blockstorage/quantumtri/mei-bali/api/resources/views/exports/base.blade.php ENDPATH**/ ?>