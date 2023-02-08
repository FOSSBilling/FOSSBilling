import Autoformat from '@ckeditor/ckeditor5-autoformat/src/autoformat';
import AutoImage  from '@ckeditor/ckeditor5-image/src/autoimage';
import BlockQuote from '@ckeditor/ckeditor5-block-quote/src/blockquote';
import Bold from '@ckeditor/ckeditor5-basic-styles/src/bold';
import ClassicEditorBase from '@ckeditor/ckeditor5-editor-classic/src/classiceditor';
import Code from '@ckeditor/ckeditor5-basic-styles/src/code';
import Essentials from '@ckeditor/ckeditor5-essentials/src/essentials';
import Heading from '@ckeditor/ckeditor5-heading/src/heading';
import Image from '@ckeditor/ckeditor5-image/src/image';
import ImageStyle from '@ckeditor/ckeditor5-image/src/imagestyle';
import ImageInsertViaUrl from '@ckeditor/ckeditor5-image/src/imageinsertviaurl';
import ImageToolbar from '@ckeditor/ckeditor5-image/src/imagetoolbar';
import Italic from '@ckeditor/ckeditor5-basic-styles/src/italic';
import Link from '@ckeditor/ckeditor5-link/src/link';
import List from '@ckeditor/ckeditor5-list/src/list';
import Markdown from '@ckeditor/ckeditor5-markdown-gfm/src/markdown';
import Paragraph from '@ckeditor/ckeditor5-paragraph/src/paragraph';
import PasteFromOffice from '@ckeditor/ckeditor5-paste-from-office/src/pastefromoffice';
import SourceEditing from '@ckeditor/ckeditor5-source-editing/src/sourceediting';
import Strikethrough from '@ckeditor/ckeditor5-basic-styles/src/strikethrough';
import Table from '@ckeditor/ckeditor5-table/src/table';
import TableToolbar from '@ckeditor/ckeditor5-table/src/tabletoolbar';
import TextTransformation from '@ckeditor/ckeditor5-typing/src/texttransformation';
import Underline from '@ckeditor/ckeditor5-basic-styles/src/underline';

class CKEditor extends ClassicEditorBase {}

CKEditor.builtinPlugins = [
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
      'link', 'insertTable', 'blockQuote',
      '|',
      'insertImage', 'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText'
    ],
  },
  image: {
    toolbar: ['imageTextAlternative']
  },
  table: {
    defaultHeadings: {rows: 1},
    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
  },
};

export default CKEditor;
