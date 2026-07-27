# Scene Rendering Rules

Supported layers are brand, text, asset, data, subtitle, audio cue, and placeholder. Position, scale, opacity, and rotation remain bounded by `scenes/scene-model.json`. SVG is fetched by canonical path, parsed, stripped of scripts, foreignObject, embedded images, external references, and event attributes, then mounted without modifying source bytes.
