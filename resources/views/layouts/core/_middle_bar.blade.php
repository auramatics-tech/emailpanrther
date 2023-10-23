<div class="middle-bar pt-1">
    <div class="middle-bar-head px-1">
        <button class="btn btn-link fs-4 middle-bar-close-button" style="box-shadow: -1rem 0rem 1rem rgba(0,0,0,.025)!important;"><span class="material-symbols-rounded">west</span></button>
    </div>
    <div class="content">
    </div>
</div>

<script>
    $(function() {
        // middle bar close
        $('.middle-bar-close-button').on('click', function() {
            hideMiddleBar();
        });
        $(document).on('mouseup', function(e) 
        {
            var container = $(".middle-bar, .middle-bar-element");

            // if the target of the click isn't the container nor a descendant of the container
            if (!container.is(e.target) && container.has(e.target).length === 0) 
            {
                hideMiddleBar();
            }
        });
    })
</script>