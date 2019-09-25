<?php 
/**
 * 
 * @since 3.0
 * 
 */

    if(isset( $field['id'] ))
        $field_id = esc_attr( $field['id'] );
    else
        $field_id = esc_attr( $key );
?>
<div class="controls" style="position: relative">
   <input type="text" class="input-text" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" id="<?php echo isset( $field['id'] ) ? esc_attr( $field['id'] ) :  esc_attr( $key ); ?>" attribute="<?php echo esc_attr( isset( $field['attribute'] ) ? $field['attribute'] : '' ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo isset( $field['value'] ) ? esc_attr( $field['value'] ) : ''; ?>" maxlength="<?php echo ! empty( $field['maxlength'] ) ? $field['maxlength'] : ''; ?>" <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> data-picker="timepicker" />
   <?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo $field['description']; ?></small><?php endif; ?>
</div>
<script>
   jQuery(document).ready(function(){
      if(jQuery(<?php echo $field_id;?> ).length > 0)
      {
         jQuery(<?php echo $field_id;?>).timepicker({ 
            'timeFormat': wp_event_manager_event_submission.i18n_timepicker_format ,
            'step': wp_event_manager_event_submission.i18n_timepicker_step,
         });
      }	
	});
</script>