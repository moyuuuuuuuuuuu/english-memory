final class CaptureImage {
  const CaptureImage(this.path);

  final String path;

  @override
  bool operator ==(Object other) => other is CaptureImage && path == other.path;

  @override
  int get hashCode => path.hashCode;
}
