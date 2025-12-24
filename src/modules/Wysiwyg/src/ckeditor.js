/**
 * Only include the features, styles, and translations needed to keep the bundle size down.
 * @see {@link https://ckeditor.com/docs/ckeditor5/latest/getting-started/setup/optimizing-build-size.html}
 */

// Code imports.
import { Autoformat } from '@ckeditor/ckeditor5-autoformat/dist/index.js';
import { AutoImage, Image, ImageStyle, ImageInsertViaUrl, ImageToolbar } from '@ckeditor/ckeditor5-image/dist/index.js';
import { BlockQuote } from '@ckeditor/ckeditor5-block-quote/dist/index.js';
import { Bold, Code, Italic, Strikethrough, Underline } from '@ckeditor/ckeditor5-basic-styles/dist/index.js';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic/dist/index.js';
import { Essentials } from '@ckeditor/ckeditor5-essentials/dist/index.js';
import { Heading } from '@ckeditor/ckeditor5-heading/dist/index.js';
import { Link } from '@ckeditor/ckeditor5-link/dist/index.js';
import { List } from '@ckeditor/ckeditor5-list/dist/index.js';
import { Markdown } from '@ckeditor/ckeditor5-markdown-gfm/dist/index.js';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph/dist/index.js';
import { PasteFromOffice } from '@ckeditor/ckeditor5-paste-from-office/dist/index.js';
import { SourceEditing } from '@ckeditor/ckeditor5-source-editing/dist/index.js';
import { Table, TableToolbar } from '@ckeditor/ckeditor5-table/dist/index.js';
import { TextTransformation } from '@ckeditor/ckeditor5-typing/dist/index.js';

// Style imports - core styles (required).
import '@ckeditor/ckeditor5-theme-lark/dist/index.css';
import '@ckeditor/ckeditor5-clipboard/dist/index.css';
import '@ckeditor/ckeditor5-core/dist/index.css';
import '@ckeditor/ckeditor5-engine/dist/index.css';
import '@ckeditor/ckeditor5-enter/dist/index.css';
import '@ckeditor/ckeditor5-paragraph/dist/index.css';
import '@ckeditor/ckeditor5-select-all/dist/index.css';
import '@ckeditor/ckeditor5-typing/dist/index.css';
import '@ckeditor/ckeditor5-ui/dist/index.css';
import '@ckeditor/ckeditor5-undo/dist/index.css';
import '@ckeditor/ckeditor5-upload/dist/index.css';
import '@ckeditor/ckeditor5-utils/dist/index.css';
import '@ckeditor/ckeditor5-watchdog/dist/index.css';
import '@ckeditor/ckeditor5-widget/dist/index.css';

// Style imports - plugin styles.
import '@ckeditor/ckeditor5-autoformat/dist/index.css';
import '@ckeditor/ckeditor5-image/dist/index.css';
import '@ckeditor/ckeditor5-block-quote/dist/index.css';
import '@ckeditor/ckeditor5-basic-styles/dist/index.css';
import '@ckeditor/ckeditor5-editor-classic/dist/index.css';
import '@ckeditor/ckeditor5-essentials/dist/index.css';
import '@ckeditor/ckeditor5-heading/dist/index.css';
import '@ckeditor/ckeditor5-link/dist/index.css';
import '@ckeditor/ckeditor5-list/dist/index.css';
import '@ckeditor/ckeditor5-markdown-gfm/dist/index.css';
import '@ckeditor/ckeditor5-paste-from-office/dist/index.css';
import '@ckeditor/ckeditor5-source-editing/dist/index.css';
import '@ckeditor/ckeditor5-table/dist/index.css';

// Default/base editor configuration.
export default class CKEditor extends ClassicEditor {
  static builtinPlugins = [
    Autoformat,
    AutoImage,
    BlockQuote,
    Bold,
    Code,
    Essentials,
    Heading,
    Image,
    ImageStyle,
    ImageInsertViaUrl,
    ImageToolbar,
    Italic,
    Link,
    List,
    Markdown,
    Paragraph,
    PasteFromOffice,
    SourceEditing,
    Strikethrough,
    Table,
    TableToolbar,
    TextTransformation,
    Underline,
  ];

  static defaultConfig = {
    licenseKey: 'GPL',
    toolbar: {
      items: [ 'undo', 'redo', '|', 'sourceEditing', '|', 'heading', '|',
        'bold', 'italic', 'underline', 'strikethrough', 'code', '|',
        'bulletedList', 'numberedList', '|', 'link', 'insertTable', 'blockQuote', '|',
        'insertImage', 'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText'
      ],
    },
    image: {
      toolbar: [ 'imageTextAlternative' ]
    },
    table: {
      defaultHeadings: {
        rows: 1
      },
      contentToolbar: [ 'tableColumn', 'tableRow', 'mergeTableCells' ],
    },
  };
}
