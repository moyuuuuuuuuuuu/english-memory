# Flutter Local OCR Design

**Date:** 2026-07-23  
**Status:** Approved for implementation planning

## Goal

Add the local capture workflow for Android and iOS. An authenticated learner can type English manually, take a photo, or select an existing photo; crop the image; recognize English locally; edit the result; choose the content type and mnemonic style; and produce a validated local draft.

This delivery does not submit the draft to `POST /api/memory-cards`. Card submission, generation progress, and generation retry remain stage 8 work so that capture and remote job state do not become one coupled state machine.

## Product Flow

The Capture destination starts with an editable English field and three entry points:

1. Manual input.
2. Take a photo.
3. Choose a photo.

Taking or choosing a photo opens a free-form cropper. Cancelling either image selection or cropping returns to Capture without changing the current draft. A successful crop immediately starts local Latin-script recognition.

After recognition, the learner sees the cropped-image preview and an editable multiline text field. Recognition performs only conservative normalization:

- normalize line endings;
- remove leading and trailing whitespace;
- trim trailing spaces on individual lines;
- collapse three or more consecutive blank lines to one blank line.

It does not rewrite capitalization, punctuation, spelling, or line order.

The confirmation form contains:

- content type: `word` or `sentence`;
- memory style: `auto`, `phonetic_story`, or `semantic_scene`;
- a Continue button that validates and emits a `CaptureDraft`.

For this delivery, Continue shows a stable Chinese notice that remote generation will be connected in the next stage. It does not send a network request or persist a pending operation.

## Recommended Dependencies

- `image_picker` for camera and photo-library acquisition.
- `image_cropper` for the native Android/iOS crop interface.
- `google_mlkit_text_recognition` with only the default Latin model for on-device OCR.

These packages remain behind application-owned interfaces. Presentation and controller code must not import plugin APIs, which keeps tests deterministic and permits future replacement.

The dependency versions selected during implementation must be compatible with Flutter 3.44.7 and Dart 3.12.2. The design review observed current compatible releases `image_picker 1.2.3`, `image_cropper 12.2.1`, and `google_mlkit_text_recognition 0.16.0`; the lockfile is the final version authority.

## Architecture

### Domain

`CaptureDraft` is immutable and contains:

- normalized `text`;
- `CaptureContentType` (`word`, `sentence`);
- `CaptureMemoryStyle` (`auto`, `phoneticStory`, `semanticScene`).

It validates non-empty text. A word is one non-whitespace token. A sentence contains multiple tokens or sentence punctuation. The UI may suggest a content type from the edited text, but the learner's explicit selection remains authoritative.

Enums serialize to the backend values already accepted by the API:

- `word`, `sentence`;
- `auto`, `phonetic_story`, `semantic_scene`.

### Service Contracts

`ImageSourceService` exposes camera and gallery selection and returns an application-owned image reference or `null` on cancellation.

`ImageCropService` accepts that reference and returns a cropped application-owned image reference or `null` on cancellation.

`TextRecognitionService` accepts the cropped reference and returns recognized text. The production adapter owns one Latin `TextRecognizer`, processes local files, and closes native resources when disposed.

The application-owned image reference contains only the local path needed for this mobile-only delivery. Images remain temporary and are not copied into durable app storage.

### Controller

`CaptureController` is a `ChangeNotifier` with explicit states:

- `CaptureReady`: editable draft, optional cropped preview, selected content type and memory style;
- `CaptureSelectingImage`;
- `CaptureCroppingImage`;
- `CaptureRecognizingText`;
- `CaptureFailure`: stable Chinese message plus the last editable draft.

The controller coordinates source selection, crop, recognition, editing, selections, retry, and cancellation. It prevents concurrent acquisition/OCR runs. Plugin exceptions are mapped to stable application failures; raw platform errors are never shown.

Manual edits are stored in controller state so tab changes preserve them through the existing `IndexedStack`. A cancelled picker or cropper restores the previous ready state byte-for-byte.

### Presentation

`CapturePage` becomes stateful only through its injected controller. It uses `ListenableBuilder` and contains no plugin calls.

The page follows the existing warm-white champagne theme:

- a prominent editable field remains the visual focus;
- camera and gallery are equally discoverable secondary actions;
- processing states use restrained progress feedback;
- the cropped preview is bounded and removable;
- selectors use Material 3 segmented controls or choice chips;
- validation and OCR errors use semantic text and remain readable by screen readers.

The page remains usable with the keyboard open and on narrow devices. Buttons are disabled while acquisition, cropping, or recognition is active.

## Error Handling

The workflow distinguishes:

- user cancellation: no error and no draft loss;
- camera or photo permission denial: explain how to grant access in system settings without opening settings automatically;
- unavailable camera: offer gallery and manual input;
- crop failure: preserve the previous draft and allow retry;
- OCR returns no usable English: retain the cropped preview, keep the existing text, and ask the learner to edit manually or choose another image;
- OCR platform failure: preserve the previous draft and show a generic local-recognition retry message.

Only Latin-script recognition is enabled. Non-English text may be returned if the native recognizer interprets it as Latin noise; therefore the controller filters results by requiring at least one ASCII letter but does not aggressively delete other characters from otherwise valid recognized text.

## Platform Configuration

Android:

- use the minimum SDK required by the selected `image_picker` release;
- register the uCrop activity required by `image_cropper`;
- retain the current single-task-compatible activity launch mode;
- use the default bundled Latin ML Kit recognizer.

iOS:

- add Chinese `NSCameraUsageDescription` and `NSPhotoLibraryUsageDescription`;
- raise the deployment target only to the minimum required by the chosen plugins;
- use the default Latin recognizer and add no Chinese/Japanese/Korean model pods.

The implementation plan must include verification of generated Android/iOS plugin configuration even though iOS signing is outside the Linux environment.

## Testing

Domain tests cover enum serialization, normalization, valid drafts, empty text, and word/sentence suggestion.

Controller tests use fakes and cover:

- manual editing;
- camera and gallery source choice;
- picker cancellation;
- crop cancellation;
- recognition success;
- empty/non-English recognition;
- picker, crop, and OCR failures;
- previous-draft preservation;
- duplicate-operation prevention;
- content-type and memory-style changes;
- draft emission.

Widget tests cover:

- all three entry points;
- loading and disabled states;
- editable OCR output;
- preview removal;
- selectors;
- validation and stable Chinese error messages;
- Continue producing a local draft without any network request;
- Capture state preservation while switching tabs.

Platform-adapter tests verify request mapping where plugin platform interfaces permit fakes. Real camera, gallery, crop, and OCR behavior requires later Android device smoke testing.

## Completion Criteria

- Camera and gallery both enter the crop-and-recognize flow.
- OCR runs locally with the Latin model and does not upload an image.
- Cancellation and failures never erase an existing draft.
- Recognized text remains editable before confirmation.
- Content type and memory style serialize to existing backend values.
- Continue emits only a validated local draft in this delivery.
- Flutter unit/widget tests and `flutter analyze` pass.
- `PROJECT_PLAN.md` records the exact new Flutter baseline and the next handoff at reliable pending synchronization.
