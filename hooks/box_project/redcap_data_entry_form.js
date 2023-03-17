$(function() {
    // none of this matters if the target field name is not on the form
    if ($("input[name='box_name']").length !== 1) return;
    // exit if the calculated source field also does not exist on the form
    if ($("input[name='box_name_calc']").length !== 1) return;
    // disable autocomplete on this field
    $("input[name='box_name']").prop("autocomplete", "off");
    // define change handler for the form fields, so we can know indirectly when [box_name_calc] changes
    $(document.form).on("change", function(e) {
        // if any field but [box_name_calc] or the secondary unique field were changed
        // force [box_name_calc] change event, since CALCTEXT (i.e. readonly) doesn't inherently trigger it
        switch (e.target.name) {
            case "box_name_calc":
                // assign its value to the secondary unique field
                $("input[name='box_name']").val(document.form["box_name_calc"].value);
                document.getElementsByName("box_name")[0].onblur();
                break;
            case "box_name":
                // do nothing
                break;
            default:
                // trigger [box_name_calc] change event
                $("input[name='box_name_calc']").change();
                break;
        }
    });
});