import Autoformat from '@ckeditor/ckeditor5-autoformat/src/autoformat.js';
import AutoImage  from '@ckeditor/ckeditor5-image/src/autoimage';
import BlockQuote from '@ckeditor/ckeditor5-block-quote/src/blockquote.js';
import Bold from '@ckeditor/ckeditor5-basic-styles/src/bold.js';
import ClassicEditorBase from '@ckeditor/ckeditor5-editor-classic/src/classiceditor.js';
import Essentials from '@ckeditor/ckeditor5-essentials/src/essentials.js';
import Heading from '@ckeditor/ckeditor5-heading/src/heading.js';
import Image from '@ckeditor/ckeditor5-image/src/image';
import ImageInsertViaUrl from '@ckeditor/ckeditor5-image/src/imageinsertviaurl';
import ImageToolbar from '@ckeditor/ckeditor5-image/src/imagetoolbar';
import Italic from '@ckeditor/ckeditor5-basic-styles/src/italic.js';
import Link from '@ckeditor/ckeditor5-link/src/link.js';
import List from '@ckeditor/ckeditor5-list/src/list.js';
import Markdown from '@ckeditor/ckeditor5-markdown-gfm/src/markdown.js';
import Paragraph from '@ckeditor/ckeditor5-paragraph/src/paragraph.js';
import PasteFromOffice from '@ckeditor/ckeditor5-paste-from-office/src/pastefromoffice';
import SourceEditing from '@ckeditor/ckeditor5-source-editing/src/sourceediting';
import Table from '@ckeditor/ckeditor5-table/src/table.js';
import TableToolbar from '@ckeditor/ckeditor5-table/src/tabletoolbar.js';
import TextTransformation from '@ckeditor/ckeditor5-typing/src/texttransformation.js';

class CKEditor extends ClassicEditorBase {}

CKEditor.builtinPlugins = [
  Autoformat,
  AutoImage,
  BlockQuote,
  Bold,
  Essentials,
  Heading,
  Image,
  ImageInsertViaUrl,
  ImageToolbar,
  Italic,
  Link,
  List,
  Markdown,
  Paragraph,
  PasteFromOffice,
  SourceEditing,
  Table,
  TableToolbar,
  TextTransformation,
];

CKEditor.defaultConfig = {
  toolbar: {
    items: [
      'undo', 'redo',
      '|',
      'sourceEditing',
      '|',
      'heading',
      '|',
      'bold', 'italic', 'underline', 'strikethrough', 'code',
      '|',
      'bulletedList', 'numberedList',
      '|',
      'link', 'insertTable', 'blockQuote', 'insertImage'
    ],
  },
  table: {
    defaultHeadings: {rows: 1},
    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
  },
};

export default CKEditor;
