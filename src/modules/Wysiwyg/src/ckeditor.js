import {
  Autoformat,
  AutoImage,
  BlockQuote,
  Bold,
  ClassicEditor as ClassicEditorBase,
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
  Underline
} from 'ckeditor5';

import 'ckeditor5/ckeditor5.css';

export default class CKEditor extends ClassicEditorBase {
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
}
