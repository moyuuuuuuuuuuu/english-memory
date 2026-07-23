import 'package:english_memory/features/capture/domain/capture_content_type.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/capture/domain/capture_memory_style.dart';
import 'package:english_memory/features/capture/domain/recognized_text_normalizer.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('enums serialize to backend values', () {
    expect(CaptureContentType.word.apiValue, 'word');
    expect(CaptureContentType.sentence.apiValue, 'sentence');
    expect(CaptureMemoryStyle.auto.apiValue, 'auto');
    expect(CaptureMemoryStyle.phoneticStory.apiValue, 'phonetic_story');
    expect(CaptureMemoryStyle.semanticScene.apiValue, 'semantic_scene');
  });

  test('normalizes whitespace without rewriting content', () {
    expect(
      normalizeRecognizedText('  Hello  \r\nworld!   \n\n\nAgain.  '),
      'Hello\nworld!\n\nAgain.',
    );
  });

  test('suggests word or sentence without overriding the selection', () {
    expect(suggestContentType('ambition'), CaptureContentType.word);
    expect(
      suggestContentType('Build a vivid memory.'),
      CaptureContentType.sentence,
    );
  });

  test('draft trims text and serializes API values', () {
    final draft = CaptureDraft(
      text: ' ambition ',
      contentType: CaptureContentType.word,
      memoryStyle: CaptureMemoryStyle.phoneticStory,
    );

    expect(draft.text, 'ambition');
    expect(draft.toJson(), {
      'text': 'ambition',
      'content_type': 'word',
      'memory_style': 'phonetic_story',
    });
  });

  test('draft rejects empty content', () {
    expect(
      () => CaptureDraft(
        text: ' \n ',
        contentType: CaptureContentType.sentence,
        memoryStyle: CaptureMemoryStyle.semanticScene,
      ),
      throwsFormatException,
    );
  });

  test('equal drafts have matching hash codes', () {
    final first = CaptureDraft(
      text: 'hello',
      contentType: CaptureContentType.word,
      memoryStyle: CaptureMemoryStyle.auto,
    );
    final second = CaptureDraft(
      text: ' hello ',
      contentType: CaptureContentType.word,
      memoryStyle: CaptureMemoryStyle.auto,
    );

    expect(first, second);
    expect(first.hashCode, second.hashCode);
  });
}
