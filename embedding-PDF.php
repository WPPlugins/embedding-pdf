<?php
/**
 * Plugin Name: Embedding PDF
 * Plugin URI: 
 * Description: Embedding PDF using &lt;object&gt Element;
 * Version: 0.0.6
 * Author: Dinesh S, IT Chimes
 * Author URI: http://www.itchimes.com
 * License: GPL2 or Later
 */

/**
 *
 * with Reference of : https://gist.github.com/fjarrett/5544469/raw/d3872536047e7a138157548c9ec8c751448276cb/gistfile1.php
 *
 * Return an ID of an attachment by searching the database with the file URL.
 *
 * First checks to see if the $url is pointing to a file that exists in
 * the wp-content directory. If so, then we search the database for a
 * partial match consisting of the remaining path AFTER the wp-content
 * directory. Finally, if a match is found the attachment ID will be
 * returned.
 *
 * @return {int} $attachment
 *
 */
function embedPDF_get_attachment_id_by_URL( $url ) {
    // Split the $url into two parts with the wp-content directory as the separator.
    $parseURL  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );

    // Return nothing if there aren't any $url parts
    if ( ! isset( $parseURL[1] ) || empty( $parseURL[1] ) ) {
        return;
    }

    // Now we're going to quickly search the DB for any attachment GUID with a partial path match.
    // Example: /uploads/2013/05/test-image.jpg
    global $wpdb;

    $prefix     = $wpdb->prefix;
    $attachment = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM " . $prefix . "posts WHERE guid RLIKE %s;",
        $parseURL[1]
    ) );
    if (! is_array($attachment)) {
        return;
    }

    return $attachment[0];
}


/**
 * Returns the ID for an attachment/media upload.
 */
function embedPDF_extract_id_from_wpURL ( $url ) {
    // The URL must be on this Wordpress site
    if ( parse_url($url, PHP_URL_HOST) != parse_url( home_url(), PHP_URL_HOST ) )
        return;

    // Gets the post ID for a given permalink
    // Can't handle pretty URLs for attachments (only the ?attachment_id=n)
    // so after this, fallback to fjarrett's code
    $id = url_to_postid( $url );
    if ($id != 0) {
        return $id;
    }

    return embedPDF_get_attachment_id_by_URL( $url );
}

/*
 * Some themes don't set content_width, so the embeds
 * will end up being quite small. This should display
 * an standard page's full width.
 */
/* Not sure if this is needed
function set_default_content_width() {
    if (!isset($content_width))
        $content_width = 850;
}
add_action( 'after_setup_theme', 'set_default_content_width' );
*/

/*
 * Returns HTML to embed a PDF using <object>, which requires no JS.
 */
function embedPDF_html_from_shortcode( $params , $content = null ) {
    extract( shortcode_atts( // Creates variables in your namespace
        array(
            'width' => '100%',
            'height'=> '500em',
            'title' => '',
            'src'   => '',
        ), $params )
    );

    $embed_html = embedPDF_embedHTML($src ? $src : $content, $title, $width, $height);
    return $embed_html ? $embed_html : $content;
}

function embedPDF_embedHTML($src, $title='', $w='100%', $h='500em') {
    // if $content is a URL pointing to an attachment page on this Wordpress
    // site then get the PDF's actual URL
    if ( $id = embedPDF_extract_id_from_wpURL($src) ) {
        $wp_post = get_post( $id );
        if ( $wp_post->post_type != 'attachment' || $wp_post->post_mime_type != 'application/pdf') {
            return;
        }

        $src = wp_get_attachment_url( $wp_post->ID );

        if (!isset($title)) {
            $title = $wp_post->post_title;
        }
    }

    // FitH will fit the page width in the embed window
    $template = '<object class="embedPDF-embed" data="%1$s#page=1&view=FitH" type="application/pdf" %3$s %4$s>
    <p><a href="%1$s">Download the PDF file%2$s.</a></p>
</object>';

    return sprintf( $template,
        esc_url($src),
        esc_attr(" $title"),
        ($w ? 'width="' . esc_attr($w) . '"' : ''),
        ($h? 'height="' . esc_attr($h) . '"' : '')
    );
}
add_shortcode( 'pdf', 'embedPDF_html_from_shortcode' );

/*
 * Adds a fake oEmbed provider for this Wordpress site
 */
function embedPDF_html_from_autoembed ($matches, $attr, $url, $rawattr) {
    $embed_html = embedPDF_embedHTML($url);
    return $embed_html ? $embed_html : $url;
}
wp_embed_register_handler('embedPDF', '#^'.home_url().'#i', 'embedPDF_html_from_autoembed');

function embedPDF_attachmentLINK ($html, $id) {
    $post = get_post( $id, ARRAY_A );
    $embed_html = embedPDF_embedHTML( $post['guid'] );

    return $embed_html ? $embed_html : $html;
}
add_filter( 'wp_get_attachment_link', 'embedPDF_attachmentLINK', null, 2 );
