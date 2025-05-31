/*globals jQuery, wp, bsi_settings */
import Picker from 'vanilla-picker';
import hex_to_rgba from './helpers/hex_to_rgba';
import decodeEntities from './helpers/decode_entities';

;(($, s) => {
  let editor = $( '#' + s );
  if (editor.length < 1) {
    return false;
  }

  let $body = $( 'body' ),
    imageeditor = editor.find( '.area--background .background' ),
    logoeditor = editor.find( '.area--logo:not(.logo-alternate) .logo' );

  $.fn.attachMediaUpload = function () {
    let wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

    // Restore the main ID when the add media button is pressed
    $( 'a.add_media' ).on( 'click', () => {
      // console.log('is this really needed?');
      wp.media.model.settings.post.id = wp_media_post_id;
    } );

    return $( this ).each( (i, element) => {
      let file_frame,
        wrap = $( element ),
        input = wrap.find( 'input' ).not( '.button' ),
        preview = wrap.find( '.image-preview-wrapper img' ),
        current_image_id = input.val(),
        button = wrap.find( '.button' ).not( '.remove' ),
        remove = wrap.find( '.button.remove' );

      remove.on( 'click', function () {
        let attachment = {
          id: '0',
          url: 'data:image/png;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
        };
        preview.attr( 'src', attachment.url );
        input.trigger( 'image:select', [attachment] );
        input.val( attachment.id );
        current_image_id = attachment.id;
      } );

      // Uploading files
      button.on( 'click', (event) => {
        event.preventDefault();
        // If the media frame already exists, reopen it.
        if (!file_frame) {
          // console.log('new file_frame');
          wp.media.model.settings.post.id = current_image_id;
          // Create the media frame.
          file_frame = wp.media.frames.file_frame = wp.media( {
            title: bsi_settings.text.image_upload_title,
            button: {
              text: bsi_settings.text.image_upload_button,
            },
            library: {
              type: wrap.data( 'types' ).split( ',' )
            },
            multiple: false // Set to true to allow multiple files to be selected
          } );

          // When an image is selected, run a callback.
          file_frame.on( 'select', () => {
            // We set multiple to false so only get one image from the uploader
            let attachment = file_frame.state().get( 'selection' ).first().toJSON();
            if ('sizes' in attachment && 'og-image' in attachment.sizes) {
              attachment.url = attachment.sizes['og-image'].url;
            }
            // Do something with attachment.id and/or attachment.url here
            preview.attr( 'src', attachment.url );
            input.trigger( 'image:select', [attachment] );
            input.val( attachment.id );
            current_image_id = attachment.id;
            // Restore the main post ID
            wp.media.model.settings.post.id = wp_media_post_id;
          } ).on( 'open', () => {
            let selection = file_frame.state().get( 'selection' );
            if (current_image_id) {
              selection.add( wp.media.attachment( current_image_id ) );
            }
          } );
        }
        // Set the post ID to what we want
        // file_frame.uploader.options.uploader.params.post_id = current_image_id;
        // Open frame
        // console.log('opening file_frame');
        file_frame.open();
      } );
    } );
  };
  $.fn.BSIattachFileUpload = function () {
    let wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

    // Restore the main ID when the add media button is pressed
    $( 'a.add_media' ).on( 'click', () => {
      // console.log('is this really needed?');
      wp.media.model.settings.post.id = wp_media_post_id;
    } );

    return $( this ).each( function () {
      let file_frame,
        wrap = $( this ),
        input = wrap.find( 'input' ).not( '.button' ),
        current_image_id = input.val(),
        button = wrap.find( '.button' ).not( '.remove' );

      // Uploading files
      button.on( 'click', (event) => {
        event.preventDefault();
        // If the media frame already exists, reopen it.
        if (!file_frame) {
          // console.log('new file_frame');
          wp.media.model.settings.post.id = current_image_id;
          // Create the media frame.
          file_frame = wp.media.frames.file_frame = wp.media( {
            title: bsi_settings.text.file_upload_title,
            button: {
              text: bsi_settings.text.file_upload_button,
            },
            library: {
              type: wrap.data( 'types' ).split( ',' )
            },
            multiple: false // Set to true to allow multiple files to be selected
          } );
          // console.log(file_frame);
          // When an image is selected, run a callback.
          file_frame.on( 'select', () => {
            // We set multiple to false so only get one image from the uploader
            let attachment = file_frame.state().get( 'selection' ).first().toJSON();
            // Do something with attachment.id and/or attachment.url here
            input.trigger( 'file:select', [attachment] );
            // console.log(attachment);
            input.val( attachment.id );
            current_image_id = attachment.id;
            // Restore the main post ID
            wp.media.model.settings.post.id = wp_media_post_id;
          } ).on( 'open', () => {
            let selection = file_frame.state().get( 'selection' );
            if (current_image_id) {
              selection.add( wp.media.attachment( current_image_id ) );
            }
          } );
        }
        // Set the post ID to what we want
        // file_frame.uploader.options.uploader.params.post_id = current_image_id;
        // Open frame
        // console.log('opening file_frame');
        file_frame.open();
      } );
    } );
  };

  $( document ).ready( () => {
    let texteditor = editor.find( '.editable' );
    let texteditor_target = editor.find( 'textarea.editable-target' );

    editor.find( 'h2 .toggle' ).on( 'click touchend', (e) => {
      $( e.target ).closest( '[class^="area"]' ).toggleClass( 'closed' );
    } );

    // bugfix: compose.js is preventing things like undo.
    editor.on( 'keypress keyup keydown', (e) => {
      e.stopPropagation();
    } );

    // native JS solution for paste-fix
    texteditor.get( 0 ).addEventListener( 'paste', () => {
      // console.log('PASTE with visual editor');
      setTimeout( () => {
        // strip all HTML
        let text = texteditor.text();
        let html = texteditor.html();
        if (text !== html) {
          text = text.replace( '<br[^>]*>/g', '\n' );
          // console.log('cleaning from', html);
          // console.log('cleaning to', text);
          texteditor.text( text ).trigger( 'keyup' );
        }
      }, 250 );
    } );

    // update target when editor edited
    texteditor.on( 'blur keyup paste', () => {
      // console.log('interaction with visual editor: ', e);
      setTimeout( () => {
        texteditor_target.val( texteditor.text() );
      }, 250 );

      // once we edit, remove the automatic update
      editor.removeClass( 'auto-title' );
    } );

    texteditor_target.on( 'blur keyup input', () => {
      // console.log('interaction with hidden field: ', e);
      texteditor_target.val( texteditor_target.val().replace( /&nbsp;<br/g, '<br' ) );
      texteditor_target.val( texteditor_target.val().replace( /&nbsp;\n/g, '\n' ) );
      texteditor.text( texteditor_target.val() );

      // once we edit, remove the automatic update
      editor.removeClass( 'auto-title' );
    } );

    // update editor when target edited
    texteditor_target.on( 'blur keyup paste', () => {
      // console.log('copy hidden field to visual: ', e);
      texteditor.text( decodeEntities( texteditor_target.val() ) );
    } ).trigger( 'paste' );

    // text color
    editor.find( '#color' ).on( 'keyup blur paste input', function () {
      editor.get( 0 ).style.setProperty( '--text-color', hex_to_rgba( $( this ).val() ) );
    } );
    // text background color
    editor.find( '#background_enabled,#background_color' ).on( 'keyup blur paste input change', () => {
      let use_background = editor.find( '#background_enabled' ).is( ':checked' );
      editor.toggleClass( 'with-text-background', use_background );
      editor.get( 0 ).style.setProperty( '--text-background', hex_to_rgba( editor.find( '#background_color' ).val() ) );
    } ).trigger( 'blur' );
    // text shadow options
    editor.find( '#text_shadow_color' ).on( 'keyup blur paste input', function () {
      editor.get( 0 ).style.setProperty( '--text-shadow-color', hex_to_rgba( $( this ).val() ) );
    } ).trigger( 'blur' );
    editor.find( '#text_shadow_top' ).on( 'keyup blur paste input', function () {
      editor.get( 0 ).style.setProperty( '--text-shadow-top', parseInt( $( this ).val(), 10 ) + 'px' );
    } ).trigger( 'blur' );
    editor.find( '#text_shadow_left' ).on( 'keyup blur paste input', function () {
      editor.get( 0 ).style.setProperty( '--text-shadow-left', parseInt( $( this ).val(), 10 ) + 'px' );
    } ).trigger( 'blur' );
    editor.find( '#text_shadow_enabled' ).on( 'change', function () {
      if ($( this ).is( ':checked' )) {
        editor.get( 0 ).style.setProperty( '--text-shadow-color', hex_to_rgba( '#555555DD' ) );
      } else {
        editor.get( 0 ).style.setProperty( '--text-shadow-color', hex_to_rgba( '#00000000' ) );
      }
    } ).trigger( 'change' );
    editor.find( '#disabled' ).on( 'change', function () {
      editor.toggleClass( 'bsi-disabled', $( this ).is( ':checked' ) );
    } );

    // positions
    $( '.wrap-position-grid input' ).on( 'change', function () {
      let c = $( this ).closest( '.wrap-position-grid' );
      let n = c.data( 'name' );
      editor.removeClass( (index, className) => {
        return (className.match( new RegExp( '(^|\\s)' + n + '-\\S+', 'g' ) ) || []).join( ' ' );
      } ).addClass( n + '-' + c.find( 'input:checked' ).attr( 'value' ) );
    } ).trigger( 'change' );

    // logo size
    $( '#image_logo_size' ).on( 'keyup blur paste input', function () {
      let v = parseInt( '0' + $( this ).val(), 10 );
      let logo_min = parseInt( $( this ).attr( 'min' ), 10 );
      let logo_max = parseInt( $( this ).attr( 'max' ), 10 );
      if (v < logo_min) {
        v = logo_min;
      }
      if (v > logo_max) {
        v = logo_max;
      }
      editor.get( 0 ).style.setProperty( '--logo-scale', v );

    } ).trigger( 'blur' );

    // font size
    $( '#text__font_size' ).on( 'keyup blur paste input', function () {
      let v = parseInt( '0' + $( this ).val(), 10 );
      let fs_min = parseInt( $( this ).attr( 'min' ), 10 );
      let fs_max = parseInt( $( this ).attr( 'max' ), 10 );
      if (v < fs_min) {
        v = fs_min;
      }
      if (v > fs_max) {
        v = fs_max;
      }
      editor.get( 0 ).style.setProperty( '--font-size', v + 'px' );
      editor.get( 0 ).style.setProperty( '--line-height', (v * 1.25) + 'px' );

    } ).trigger( 'blur' );

    // sliders
    $( '.add-slider' ).each( function () {
      let $input = $( this ).find( 'input' );
      $input.attr( 'size', 4 ).on( 'blur change', function () {
        $( this ).next( '.a-slider' ).slider( 'value', parseInt( $( this ).val(), 10 ) );
        $( this ).trigger( 'input' );
      } ).after( '<div class="a-slider"></div>' );

      $input.next( '.a-slider' ).slider( {
        min: parseInt( $input.attr( 'min' ), 10 ),
        max: parseInt( $input.attr( 'max' ), 10 ),
        step: parseInt( $input.attr( 'step' ), 10 ),
        value: parseInt( $input.attr( 'value' ), 10 ),
        change: function (event, ui) {
          $( this ).prev( 'input' ).val( ui.value ).trigger( 'input' );
        },
        slide: function (event, ui) {
          $( this ).prev( 'input' ).val( ui.value ).trigger( 'input' );
        }
      } );
    } );

    editor.find( '#image' ).on( 'image:select', (event, attachment) => {
      imageeditor.get( 0 ).style.backgroundImage = 'url("' + attachment.url + '")';
    } );

    editor.find( '#image_logo' ).on( 'image:select', (event, attachment) => {
      if ('id' in attachment && parseInt( '' + attachment.id, 10 ) > 0) {
        editor.get( 0 ).style.setProperty( '--logo-width', attachment.width );
        editor.get( 0 ).style.setProperty( '--logo-height', attachment.height );
        logoeditor.get( 0 ).style.backgroundImage = 'url("' + attachment.url + '")';
        editor.addClass( 'with-logo' );
      } else {
        editor.get( 0 ).style.setProperty( '--logo-width', 410 ); // this is the example logo
        editor.get( 0 ).style.setProperty( '--logo-height', 82 );
        logoeditor.get( 0 ).style.backgroundImage = '';
        editor.removeClass( 'with-logo' );
      }
    } );

    editor.find( '#text__ttf_upload' ).on( 'file:select', function (event, attachment) {
      $( this ).parent().find( '.filename' ).html( attachment.filename );
    } );

    editor.find( 'i.toggle-comment,i.toggle-info' ).on( 'click touchend', function () {
      $( this ).toggleClass( 'active' );
    } );

    editor.find( '#text__font' ).on( 'keyup blur paste input change', function () {
      editor.get( 0 ).style.setProperty( '--text-font', $( this ).val() );
      editor.attr( 'data-font', $( this ).val() );
    } ).trigger( 'blur' ); // font face is defined in *admin.php

    editor.find( '#text_enabled' ).on( 'change', function () {
      $( '.area--text' ).toggleClass( 'invisible', !$( this ).is( ':checked' ) );
    } ).trigger( 'change' ); // font face is defined in *admin.php

    $( '.input-color', editor ).each( function () {
      let $input = $( this ).find( 'input' );
      new Picker( {
        parent: this,
        popup: 'top',
        color: $input.val(),
        onChange: (color) => {
          $input.val( color.hex.toUpperCase() ).parent().get( 0 ).style.setProperty( '--the-color', hex_to_rgba( color.hex ) );
          $input.trigger( 'blur' );
        },
        // onDone: function(color){},
        // onOpen: function(color){},
        // onClose: function(color){}
      } );
    } );

    let getFeaturedImage = function () {
    };
    // window.getFeaturedImage = getFeaturedImage;
    let subscribe, state = {yoast: false, rankmath: false, featured: false};

    if ($body.is( '.block-editor-page' )) {

      let select = wp.data.select;
      subscribe = wp.data.subscribe;

      let _coreDataSelect, _coreEditorSelect;

      let getMediaById = (mediaId) => {
        if (!_coreDataSelect) {
          _coreDataSelect = select( 'core' );
        }

        return _coreDataSelect.getMedia( mediaId );
      };

      let getPostAttribute = (attribute) => {
        if (!_coreEditorSelect) {
          _coreEditorSelect = select( 'core/editor' );
        }

        return _coreEditorSelect.getEditedPostAttribute( attribute );
      };

      getFeaturedImage = () => {
        const featuredImage = getPostAttribute( 'featured_media' );
        if (featuredImage) {
          const mediaObj = getMediaById( featuredImage );

          if (mediaObj) {
            if (bsi_settings.image_size_name in mediaObj.media_details.sizes) {
              return mediaObj.media_details.sizes[bsi_settings.image_size_name].source_url;
            }
            return mediaObj.source_url;
          }
        }

        return null;
      };
    } else {
      getFeaturedImage = () => {
        return $( '#set-post-thumbnail img' ).attr( 'src' ) || '';
      };
    }

    // yoast?? no events on the input, use polling
    let getYoastFacebookImage = () => {
      let url;
      let $field = $( '#facebook-url-input-metabox' );
      let $preview = $( '#wpseo-section-social > div:n' + 'th(0) .yoast-image-select__preview--image' );
      if (!$field.length && !$preview.length) {
        return false;
      }
      url = $field.val();
      if (!url) {
        url = $preview.attr( 'src' );
      }

      return url;
    };

    // rankmath?? no events on the input, use polling
    let getRankMathFacebookImage = () => {
      let url;
      let $preview = $( '.rank-math-social-preview-facebook .rank-math-social-image-thumbnail' );
      if (!$preview.length) {
        url = state.rankmath;
      } else if ($preview.attr( 'src' ).match( /wp-content\/plugins\/seo-by-rank-math\// )) {
        url = false;
      } else {
        url = $preview.attr( 'src' );
      }

      return url;
    };
    state = {yoast: getYoastFacebookImage(), rankmath: getYoastFacebookImage(), featured: getFeaturedImage()};

    // window.i_state = state;
    // window.i_gyfi = getYoastFacebookImage;
    // window.i_grmfi = getRankMathFacebookImage;
    // window.i_gfi = getFeaturedImage;

    let external_images_maybe_changed = () => {
      setTimeout( () => {
        let url;
        // yoast
        if ($( '.area--background-alternate.image-source-yoast' ).length) {
          url = getYoastFacebookImage();
          if (state.yoast !== url) {
            state.yoast = url;
            if (url) {
              $( '.area--background-alternate.image-source-yoast .background' ).get( 0 ).style.backgroundImage = 'url("' + url + '")';
            } else {
              $( '.area--background-alternate.image-source-yoast .background' ).get( 0 ).style.backgroundImage = '';
            }
          }
        }

        // rankmath
        if ($( '.area--background-alternate.image-source-rankmath' ).length) {
          url = getRankMathFacebookImage();
          if (state.rankmath !== url) {
            state.rankmath = url;
            if (url) {
              $( '.area--background-alternate.image-source-rankmath .background' ).get( 0 ).style.backgroundImage = 'url("' + url + '")';
            } else {
              $( '.area--background-alternate.image-source-rankmath .background' ).get( 0 ).style.backgroundImage = '';
            }
          }
        }

        // thumbnail
        if ($( '.area--background-alternate.image-source-thumbnail' ).length) {
          url = getFeaturedImage();
          if (state.featured !== url) {
            state.featured = url;
            if (url) {
              $( '.area--background-alternate.image-source-thumbnail .background' ).get( 0 ).style.backgroundImage = 'url("' + url + '")';
            } else {
              $( '.area--background-alternate.image-source-thumbnail .background' ).get( 0 ).style.backgroundImage = '';
            }
          }
        }
      }, 500 );
    };

    if ($body.is( '.block-editor-page' )) {
      let debounce;
      subscribe( () => {
        if (debounce) {
          clearTimeout( debounce );
        }
        debounce = setTimeout( () => {
          external_images_maybe_changed();
        }, 1000 );
      } );
    }
    setInterval( external_images_maybe_changed, 5000 );

    // monitor available space for editor
    // you might be wondering, why?
    // we use zoom to scale the entire interface because otherwise we would have to size all images and the text based on viewport width... which is even more crap
    let monitor_space = () => {
      let w = $( '#branded-social-images' ).outerWidth();
      if (w < 600) {
        editor.get( 0 ).style.setProperty( '--editor-scale', (w - 26) / 600 * 0.5 );
      } else {
        editor.get( 0 ).style.setProperty( '--editor-scale', 0.5 );
      }
    };
    $( window ).on( 'resize', monitor_space );
    setTimeout( monitor_space, 1000 );

    // monitor title
    let title_field = $( '.wp-admin,.block-editor-page' ).filter( '.post-new-php,.edit-php,.edit-tags-php' ).find( '#post #title,.block-editor .editor-post-title textarea,#tag-name,h1.wp-block-post-title[contenteditable]' ).get( 0 );
    let nbsp='\ufeff';
    let update_auto_title = () => {
      // sure?
      if (editor.is( '.auto-title' )) {
        let input_title = $( title_field ).is( 'h1' ) ? $( title_field ).text() : $( title_field ).val().trim();
        if (nbsp === input_title) {
          input_title = '';
        }
        let new_title = bsi_settings.title_format;
        if (input_title) {
          new_title = new_title.replace( '{title}', input_title );
        }
        texteditor_target.val( new_title );
        texteditor.text( new_title );
      } else {
        $( title_field ).off( 'keyup change blur', update_auto_title );
      }
    };

    if ($body.is( '.block-editor-page' )) { // gutenberg
      subscribe( () => {
        title_field = $( '.block-editor-page .block-editor .editor-post-title textarea,#tag-name,h1.wp-block-post-title[contenteditable]' ).get( 0 );
        if (title_field && !$( title_field ).is( 'bsi-bound' )) {
          $( title_field ).addClass( 'bsi-bound' ).on( 'keyup change blur', update_auto_title ).trigger( 'keyup' );
        }
      } );
    } else { // classic editor and category
      if (title_field) {
        $( title_field ).on( 'keyup change blur', update_auto_title ).trigger( 'keyup' );
      }
    }

  } );
})( jQuery, 'branded-social-images-editor' );

// Test the decoder: console.log(decodeEntities('<h1>title</h1><p>paragraph1<p>paragraph2<p>paragraph3<p>paragraph4</p></p></p></p><script>script()</script>'));
