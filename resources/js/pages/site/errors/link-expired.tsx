import { ErrorMessage } from './message';

export default function LinkExpired() {
    return (
        <ErrorMessage title="Preview link expired">
            Preview links only work for a short time. Open the preview again
            from your event's Design page.
        </ErrorMessage>
    );
}
