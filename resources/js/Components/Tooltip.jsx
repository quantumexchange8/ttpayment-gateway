import React, { useState } from 'react';
import PropTypes from 'prop-types';

const Tooltip = ({ text, children }) => {

  const [showTooltip, setShowTooltip] = useState(false);

  return (
    <div 
      className="relative flex items-center justify-center w-6 h-6 cursor-pointer rounded-full "
      onMouseEnter={() => setShowTooltip(true)}
      onMouseLeave={() => setShowTooltip(false)}
    >
      {children}
      {showTooltip && (
        <div className="absolute top-full left-1/2 transform -translate-x-1/2 bg-black text-white text-xss font-medium px-2 py-1 rounded opacity-100 transition-opacity duration-300 z-50">
          {text}
        </div>
      )}
      
    </div>
  );
};

Tooltip.propTypes = {
  text: PropTypes.string.isRequired,
  children: PropTypes.node.isRequired,
};

export default Tooltip;
