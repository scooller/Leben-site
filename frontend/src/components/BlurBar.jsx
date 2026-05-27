import '../styles/blurbar.scss';

const LAYER_COUNT = 8;

function BlurBar() {
  return (
    <div className="blur-bar">
      {Array.from({ length: LAYER_COUNT }, (_, i) => (
        <div key={i} className="blur-bar__layer" />
      ))}
    </div>
  );
}

export default BlurBar;
